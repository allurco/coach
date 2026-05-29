{{-- Composer — single compact row: attach · input pill · icon send
     (prototype / ADR 0007). @submit does an optimistic insert: renders the
     user's orange bubble before the Livewire round-trip; the morph hook
     removes the [data-optimistic] placeholder once the server message lands. --}}
<form wire:submit="send"
      @submit="
          const ta = $el.querySelector('textarea');
          const text = (ta?.value || '').trim();
          if (!text) return;
          const thread = document.querySelector('.coach-thread');
          if (!thread) return;
          const wrap = document.createElement('div');
          wrap.className = 'msg msg-user msg-optimistic';
          wrap.dataset.optimistic = '1';
          const bubble = document.createElement('div');
          bubble.className = 'msg-bubble';
          bubble.textContent = text;
          wrap.appendChild(bubble);
          thread.appendChild(wrap);
          thread.scrollTop = thread.scrollHeight;
          if (ta) ta.value = '';
      ">
    <div class="composer">
        <button type="button"
                class="composer-attach-btn"
                @click="$root.querySelector('.filepond--browser')?.click()"
                aria-label="{{ __('coach.composer.attach') }}"
                title="{{ __('coach.composer.attach') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        </button>

        <div class="composer-field">
            {{ $this->form }}
        </div>

        <button type="submit"
                class="send-btn"
                aria-label="{{ __('coach.composer.send') }}"
                title="{{ __('coach.composer.send') }}"
                x-bind:disabled="$wire.thinking">
            @if ($thinking)
                <span class="btn-spinner"></span>
            @else
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            @endif
        </button>
    </div>
</form>
