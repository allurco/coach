{{-- Footer JS: quick-reply prompt injector + optimistic-message sweeper.
     Lives in the same blade tree (not a separate <script src>) so it
     loads on every full page mount without an additional asset round-trip. --}}
<script>
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-prompt]');
        if (btn) {
            const prompt = btn.dataset.prompt;
            const ta = document.querySelector('.composer textarea');
            if (ta) {
                ta.focus();
                ta.value = prompt;
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    // Sweep optimistic user-message placeholders after every Livewire
    // morph. By the time send() has committed, the real .msg is in
    // the rendered thread, so any [data-optimistic] sibling is a
    // stale duplicate and gets removed.
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => {
            document.querySelectorAll('[data-optimistic]').forEach(el => el.remove());
        });
    });
</script>
