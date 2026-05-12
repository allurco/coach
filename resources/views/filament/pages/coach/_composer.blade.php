{{-- Composer — textarea form + send button. The @submit handler does an
     optimistic insert: renders the user's message into the thread BEFORE
     the Livewire round-trip, so the UI feels instant. The matching
     server-rendered .msg arrives 150-300ms later and the morph hook
     (coach.blade.php script) removes the [data-optimistic] placeholder. --}}
<form wire:submit="send"
      @submit="
          const ta = $el.querySelector('textarea');
          const text = (ta?.value || '').trim();
          if (!text) return;
          const thread = document.querySelector('.coach-thread');
          if (!thread) return;
          const time = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
          const wrap = document.createElement('div');
          wrap.className = 'msg msg-optimistic';
          wrap.dataset.optimistic = '1';
          const avatar = document.createElement('div');
          avatar.className = 'msg-avatar user';
          avatar.textContent = 'R';
          const body = document.createElement('div');
          body.className = 'msg-body';
          const name = document.createElement('div');
          name.className = 'msg-name';
          name.textContent = 'Você ';
          const timeSpan = document.createElement('span');
          timeSpan.className = 'time';
          timeSpan.textContent = time;
          name.appendChild(timeSpan);
          const content = document.createElement('div');
          content.className = 'msg-content';
          content.textContent = text;
          body.appendChild(name);
          body.appendChild(content);
          wrap.appendChild(avatar);
          wrap.appendChild(body);
          thread.appendChild(wrap);
          thread.scrollTop = thread.scrollHeight;
          if (ta) ta.value = '';
      ">
    <div class="composer">
        {{ $this->form }}

        <div class="composer-actions">
            <button type="button"
                    class="composer-attach-btn"
                    @click="$root.querySelector('.filepond--browser')?.click()"
                    aria-label="{{ __('coach.composer.attach') }}"
                    title="{{ __('coach.composer.attach') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </button>
            <span class="composer-hint">
                <kbd>↵</kbd> envia · <kbd>shift</kbd>+<kbd>↵</kbd> nova linha
            </span>
            <button type="submit"
                    class="send-btn"
                    x-bind:disabled="$wire.thinking">
                @if ($thinking)
                    <span class="btn-spinner"></span>
                @else
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                @endif
                Enviar
            </button>
        </div>
    </div>
</form>
