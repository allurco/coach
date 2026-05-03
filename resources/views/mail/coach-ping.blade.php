<x-mail::message>
# {{ $heading }}

<p style="color:#737373; font-size:12px; letter-spacing:0.08em; text-transform:uppercase; margin:0 0 1.5rem; font-weight:500;">
    {{ match ($kind) {
        'morning' => 'briefing matinal',
        'weekly' => 'recap semanal',
        'stuck' => 'olha esse aqui',
        default => 'mensagem do coach',
    } }}
</p>

{!! \Illuminate\Support\Str::markdown($body) !!}

<x-mail::button :url="config('app.url')">
    Abrir o plano
</x-mail::button>

<p style="color:#a3a3a3; font-size:11px; margin-top:2rem;">
    Coach pessoal · {{ now()->format('d/m/Y H:i') }}
</p>
</x-mail::message>
