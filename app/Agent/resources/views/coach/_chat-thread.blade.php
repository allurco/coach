{{-- Chat thread — greeting/empty state OR list of messages + thinking bubble --}}
<div class="coach-thread"
     x-data="{}"
     x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
     x-effect="$wire.messages; $nextTick(() => $el.scrollTop = $el.scrollHeight)">

    @if (empty($messages))
        <div class="msg coach-greeting-msg">
            <div class="msg-avatar coach">C</div>
            <div class="msg-body">
                <div class="msg-name">Coach</div>
                <div class="msg-content greeting-content">
                    <p class="greeting-line-1">{{ $this->userFirstName() !== '' ? __('coach.greeting_first', ['name' => $this->userFirstName()]) : __('coach.greeting_first_anon') }}</p>
                    <p class="greeting-line-2">{{ __('coach.greeting_second') }}</p>
                </div>

                @if ($this->isFirstTimer())
                    <div class="welcome-cards">
                        <div class="welcome-cards-label">{{ __('coach.welcome.how_label') }}</div>
                        <div class="welcome-cards-grid">
                            @foreach (__('coach.welcome.concepts') as $concept)
                                <div class="welcome-card">
                                    <span class="welcome-card-icon" aria-hidden="true">{{ $concept['icon'] }}</span>
                                    <div class="welcome-card-text">
                                        <div class="welcome-card-title">{{ $concept['title'] }}</div>
                                        <div class="welcome-card-body">{{ $concept['body'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="quick-replies">
                    @foreach (__($this->suggestionsKey()) as $s)
                        <button type="button"
                                class="quick-reply"
                                data-prompt="{{ $s['prompt'] }}">
                            {{ $s['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        @foreach ($messages as $index => $msg)
            @if ($msg['role'] === 'user')
                <div class="msg">
                    <div class="msg-avatar user">R</div>
                    <div class="msg-body">
                        <div class="msg-name">
                            Você
                            <span class="time">{{ $msg['time'] }}</span>
                        </div>
                        <div class="msg-content">{{ $msg['content'] }}</div>
                        @if (! empty($msg['attachments']))
                            <div class="mt-1">
                                @foreach ($msg['attachments'] as $name)
                                    <span class="attach-pill">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                        {{ $name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @elseif ($msg['role'] === 'assistant')
                <div class="msg">
                    <div class="msg-avatar coach">C</div>
                    <div class="msg-body">
                        <div class="msg-name">
                            Coach
                            <span class="time">{{ $msg['time'] }}</span>
                            <button type="button"
                                    class="msg-share-btn"
                                    wire:click="openShareModal({{ $index }})"
                                    title="{{ __('coach.share_modal.icon_label') }}"
                                    aria-label="{{ __('coach.share_modal.icon_label') }}">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            </button>
                        </div>
                        <div class="msg-content prose-coach">
                            {!! $msg['content_html'] ?? e($msg['content']) !!}
                        </div>
                    </div>
                </div>
            @else
                <div class="msg">
                    <div class="msg-avatar" style="background: #ef4444; color: white;">!</div>
                    <div class="msg-body">
                        <div class="msg-name" style="color: #ef4444;">Erro</div>
                        <div class="msg-content" style="color: #ef4444;">{{ $msg['content'] }}</div>
                    </div>
                </div>
            @endif
        @endforeach

        @if ($thinking)
            <div class="msg msg-thinking">
                <div class="msg-avatar coach">C</div>
                <div class="msg-body">
                    <div class="msg-name">Coach</div>
                    {{-- Single-line markup intentional: msg-content carries
                         white-space: pre-wrap (so streamed LLM text keeps its
                         own newlines), which would otherwise render the indented
                         child spans as empty lines and inflate the bubble. --}}
                    <div class="msg-content" x-data="{}" x-init="$nextTick(() => $el.closest('.coach-thread').scrollTop = $el.closest('.coach-thread').scrollHeight)"><span class="streaming-content" wire:stream="coach-stream"></span><span class="thinking-dots" aria-hidden="true"><span></span><span></span><span></span></span></div>
                </div>
            </div>
        @endif
    @endif
</div>
