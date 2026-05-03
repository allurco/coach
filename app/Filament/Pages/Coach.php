<?php

namespace App\Filament\Pages;

use App\Ai\Agents\FinanceCoach;
use App\Models\Action;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Throwable;

class Coach extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.coach';

    protected static ?string $slug = '/';

    protected static ?string $navigationLabel = 'Coach';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public ?string $conversationId = null;

    public array $messages = [];

    public bool $thinking = false;

    public array $conversations = [];

    public ?string $streamingText = null;

    public ?string $pendingPrompt = null;

    public array $pendingAttachments = [];

    public array $planActions = [];

    public string $planFilter = 'pendente';

    public ?int $completingActionId = null;

    public ?string $completingActionTitle = null;

    public string $completingNotes = '';

    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    public function mount(): void
    {
        $this->form->fill();
        $this->loadConversations();
        $this->loadPlan();
    }

    public function loadPlan(): void
    {
        $query = Action::query();

        if ($this->planFilter !== 'todas') {
            $query->where('status', $this->planFilter);
        }

        $this->planActions = $query
            ->orderByRaw("CASE status WHEN 'em_andamento' THEN 0 WHEN 'pendente' THEN 1 WHEN 'concluido' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->orderByRaw("CASE priority WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END")
            ->limit(100)
            ->get()
            ->map(fn (Action $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'category' => $a->category,
                'priority' => $a->priority,
                'status' => $a->status,
                'deadline' => $a->deadline?->format('d/m/Y'),
                'is_overdue' => $a->isOverdue(),
                'is_due_soon' => $a->isDueSoon(),
            ])
            ->toArray();
    }

    public function setPlanFilter(string $filter): void
    {
        $this->planFilter = $filter;
        $this->loadPlan();
    }

    public function startCompleteAction(int $id): void
    {
        $action = Action::find($id);
        if (! $action) {
            return;
        }
        $this->completingActionId = $id;
        $this->completingActionTitle = $action->title;
        $this->completingNotes = '';
    }

    public function cancelCompleteAction(): void
    {
        $this->completingActionId = null;
        $this->completingActionTitle = null;
        $this->completingNotes = '';
    }

    public function confirmCompleteAction(): void
    {
        if ($this->completingActionId === null) {
            return;
        }

        $payload = [
            'status' => 'concluido',
            'completed_at' => now(),
            'snooze_until' => null,
        ];

        $notes = trim($this->completingNotes);
        if ($notes !== '') {
            $payload['result_notes'] = $notes;
        }

        Action::where('id', $this->completingActionId)->update($payload);

        $this->cancelCompleteAction();
        $this->loadPlan();
    }

    public function snoozeAction(int $id, string $duration): void
    {
        $until = match ($duration) {
            'tomorrow' => now()->addDay(),
            '3days' => now()->addDays(3),
            'week' => now()->addWeek(),
            'month' => now()->addMonth(),
            default => null,
        };

        Action::where('id', $id)->update(['snooze_until' => $until?->toDateString()]);
        $this->loadPlan();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->hiddenLabel()
                    ->placeholder(__('coach.composer.placeholder'))
                    ->rows(3)
                    ->autofocus(),
                FileUpload::make('attachments')
                    ->hiddenLabel()
                    ->multiple()
                    ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxFiles(5)
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('coach-uploads')
                    ->preserveFilenames()
                    ->visibility('private')
                    ->openable()
                    ->downloadable()
                    ->previewable()
                    ->imagePreviewHeight('100'),
            ])
            ->statePath('data');
    }

    public function loadConversations(): void
    {
        $userId = auth()->id();

        $this->conversations = DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(40)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?: __('coach.conversations.untitled'),
                'updated_at' => $c->updated_at,
                'updated_label' => $this->humanTime($c->updated_at),
            ])
            ->toArray();
    }

    public function loadConversation(string $id): void
    {
        $userId = auth()->id();

        $exists = DB::table('agent_conversations')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            return;
        }

        $rows = DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'asc')
            ->get();

        $this->messages = $rows->map(function ($m) {
            $isAssistant = $m->role === 'assistant';
            $content = (string) $m->content;
            $attachments = [];

            if (! empty($m->attachments) && $m->attachments !== '[]') {
                $decoded = json_decode($m->attachments, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $att) {
                        $attachments[] = $att['name'] ?? $att['path'] ?? '?';
                    }
                }
            }

            return [
                'role' => $isAssistant ? 'assistant' : 'user',
                'content' => $content,
                'content_html' => $isAssistant ? Str::markdown($content, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]) : null,
                'attachments' => $attachments,
                'time' => Carbon::parse($m->created_at)->format('H:i'),
            ];
        })->toArray();

        $this->conversationId = $id;
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $userMessage = trim($data['message'] ?? '');
        $attachmentPaths = $data['attachments'] ?? [];

        if ($userMessage === '' && empty($attachmentPaths)) {
            return;
        }

        $attachmentNames = array_map(
            fn ($p) => basename($p),
            is_array($attachmentPaths) ? $attachmentPaths : [],
        );

        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage ?: __('coach.attachments.sent_indicator'),
            'attachments' => $attachmentNames,
            'time' => now()->format('H:i'),
        ];

        $this->thinking = true;
        $this->pendingPrompt = $userMessage;
        $this->pendingAttachments = is_array($attachmentPaths) ? $attachmentPaths : [];
        $this->form->fill(['message' => '', 'attachments' => []]);

        // Defer AI processing to a second Livewire request so the user message
        // and "thinking" state render immediately. The frontend triggers runAi().
        $this->js('$wire.runAi()');
    }

    public function runAi(): void
    {
        $userMessage = (string) $this->pendingPrompt;
        $attachmentPaths = $this->pendingAttachments ?? [];

        $this->pendingPrompt = null;
        $this->pendingAttachments = [];

        if (! $this->thinking) {
            return;
        }

        try {
            $coach = new FinanceCoach;

            if ($this->conversationId) {
                $coach = $coach->continue($this->conversationId, as: auth()->user());
            } else {
                $coach = $coach->forUser(auth()->user());
            }

            $documents = [];
            foreach ($attachmentPaths as $relativePath) {
                if (! Storage::disk('local')->exists($relativePath)) {
                    Log::warning('Coach attachment missing on disk', ['path' => $relativePath]);

                    continue;
                }

                $absolutePath = Storage::disk('local')->path($relativePath);
                $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

                $documents[] = match ($extension) {
                    'png', 'jpg', 'jpeg', 'webp', 'gif' => Image::fromPath($absolutePath),
                    default => Document::fromPath($absolutePath),
                };
            }

            Log::info('Coach prompt', [
                'message' => $userMessage,
                'attachment_count' => count($documents),
                'attachment_paths' => $attachmentPaths,
            ]);

            $promptToSend = $userMessage ?: __('coach.attachments.analyze_default');

            if (! empty($documents)) {
                $promptToSend .= "\n\n---\n\n"
                    ."FORMATO OBRIGATÓRIO da sua resposta:\n\n"
                    ."1️⃣ PRIMEIRO a tabela markdown estruturada do documento:\n\n"
                    ."| Campo | Valor |\n"
                    ."|---|---|\n"
                    ."| Tipo | (fatura cartão / boleto / extrato / certidão / contrato / nota fiscal / DARF / GPS / DAS / outro) |\n"
                    ."| Emissor | (banco/empresa/órgão) |\n"
                    ."| Pagador | (nome ou CPF/CNPJ) |\n"
                    ."| Categoria | PF / PJ / Híbrido |\n"
                    ."| Valor total | R\$ X,XX |\n"
                    ."| Vencimento | DD/MM/AAAA |\n"
                    ."| Data emissão | DD/MM/AAAA |\n"
                    ."| Identificador | (número/código) |\n"
                    ."| Pontos críticos | (parcelamento/juros/atraso/observações) |\n\n"
                    ."2️⃣ DEPOIS da tabela, faça uma ANÁLISE QUALITATIVA do conteúdo (NÃO PULE):\n"
                    ."   - Se for fatura de cartão: liste 3-5 lançamentos mais relevantes (valores acima da média, padrões, recorrentes).\n"
                    .'     IDENTIFIQUE explicitamente despesas que parecem PJ no cartão pessoal: Google Cloud, AWS, Vercel, GitHub, '
                    ."     Microsoft, Figma, Workspace, hosting, SaaS de dev. Some o total dessas e diga o que faria sentido migrar.\n"
                    ."   - Se for extrato: identifique padrões de gasto — categorias recorrentes, picos, sangrias.\n"
                    ."   - Se for boleto/imposto: contexto fiscal e implicações de atraso.\n"
                    ."   Use bullets ou parágrafos curtos. Não tem que ser exaustivo, mas tem que ser ÚTIL.\n\n"
                    ."3️⃣ DEPOIS, 1-2 frases sobre o que o usuário deveria fazer.\n\n"
                    ."4️⃣ POR ÚLTIMO chame RememberFact com label e summary.\n\n"
                    .'REGRA CRÍTICA: a tabela + análise + texto devem aparecer na sua mensagem visível PRIMEIRO. '
                    .'NUNCA termine apenas com tool call sem texto. '
                    .'NÃO PULE a análise qualitativa — é o que mais importa pro usuário.';
            }

            $accumulated = '';
            $this->streamingText = '';

            $toolLabels = (array) __('coach.tool_labels');

            $stream = $coach->stream(
                $promptToSend,
                attachments: $documents,
                provider: Lab::Gemini,
                model: 'gemini-2.5-flash',
            );

            $batch = ['name' => null, 'calls' => 0, 'ok' => 0];
            $toolActivity = [];

            $flushBatch = function () use (&$batch, &$toolActivity, $toolLabels) {
                if ($batch['name'] === null) {
                    return;
                }
                $label = $toolLabels[$batch['name']] ?? $batch['name'];
                $count = $batch['calls'];
                $allOk = $batch['ok'] === $count;
                $icon = $allOk ? '✓' : '⚠';
                $suffix = $count > 1 ? " ({$count}x)" : '';
                $this->stream(to: 'coach-stream', content: " {$icon}{$suffix}\n\n");
                $toolActivity[] = ['name' => $batch['name'], 'count' => $count, 'ok' => $batch['ok']];
                $batch = ['name' => null, 'calls' => 0, 'ok' => 0];
            };

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $flushBatch();
                    $accumulated .= $event->delta;
                    $this->stream(to: 'coach-stream', content: $event->delta);
                } elseif ($event instanceof ToolCall) {
                    if ($batch['name'] !== null && $batch['name'] !== $event->toolCall->name) {
                        $flushBatch();
                    }
                    if ($batch['name'] === null) {
                        $label = $toolLabels[$event->toolCall->name] ?? $event->toolCall->name;
                        $this->stream(to: 'coach-stream', content: "\n⏳ {$label}…");
                        $batch['name'] = $event->toolCall->name;
                    }
                    $batch['calls']++;
                } elseif ($event instanceof ToolResult) {
                    if ($event->successful) {
                        $batch['ok']++;
                    }
                }
            }

            $flushBatch();

            // Capture the conversation ID after streaming so the next turn continues it.
            $this->conversationId = $coach->currentConversation() ?? $this->conversationId;

            $rawText = trim($accumulated !== '' ? $accumulated : (string) ($stream->text ?? ''));

            if ($rawText === '') {
                $rawText = $this->summarizeToolActivity($toolActivity);
            }

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $rawText,
                'content_html' => Str::markdown($rawText, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
                'attachments' => [],
                'time' => now()->format('H:i'),
            ];

            $this->streamingText = null;
            $this->loadConversations();
            $this->loadPlan();
        } catch (Throwable $e) {
            $this->messages[] = [
                'role' => 'error',
                'content' => __('coach.errors.prefix').$e->getMessage(),
                'attachments' => [],
                'time' => now()->format('H:i'),
            ];
        }

        $this->thinking = false;
    }

    public function newConversation(): void
    {
        $this->messages = [];
        $this->conversationId = null;
        $this->form->fill();
    }

    public function resetConversation(): void
    {
        $this->newConversation();
    }

    protected function summarizeToolActivity(array $activity): string
    {
        if (empty($activity)) {
            return __('coach.errors.no_text_returned');
        }

        $created = 0;
        $updated = 0;
        $remembered = 0;

        foreach ($activity as $entry) {
            match ($entry['name']) {
                'CreateAction' => $created += $entry['ok'],
                'UpdateAction' => $updated += $entry['ok'],
                'RememberFact' => $remembered += $entry['ok'],
                default => null,
            };
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = $created === 1
                ? __('coach.recap.created_one')
                : __('coach.recap.created_many', ['count' => $created]);
        }
        if ($updated > 0) {
            $parts[] = $updated === 1
                ? __('coach.recap.updated_one')
                : __('coach.recap.updated_many', ['count' => $updated]);
        }
        if ($remembered > 0) {
            $parts[] = $remembered === 1
                ? __('coach.recap.remembered_one')
                : __('coach.recap.remembered_many', ['count' => $remembered]);
        }

        if (empty($parts)) {
            return __('coach.recap.done');
        }

        return __('coach.recap.with_results', ['parts' => implode(', ', $parts)]);
    }

    protected function humanTime($timestamp): string
    {
        $date = Carbon::parse($timestamp);
        $now = now();

        if ($date->isSameDay($now)) {
            return $date->format('H:i');
        }
        if ($date->isYesterday()) {
            return 'ontem';
        }

        $days = (int) floor($date->diffInDays($now, true));
        if ($days < 7) {
            return $days.'d';
        }

        return $date->format('d/m');
    }
}
