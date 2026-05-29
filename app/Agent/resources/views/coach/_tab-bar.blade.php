{{-- Workspace tab bar (prototype / ADR 0007): Chat · the active pack's
     primary Tool · Mais. The primary slot changes with the goal's pack
     (Finance → Budget); "Mais" opens a sheet of the remaining Tools.
     Driven by the ToolRegistry via workspaceTools()/primaryToolKey(). --}}
@php
    $primaryKey = $this->primaryToolKey();
    $primary = $primaryKey ? collect($this->workspaceTools())->firstWhere('key', $primaryKey) : null;
    $moreActive = $activeTool !== null && $activeTool !== $primaryKey;
@endphp
<nav class="tool-tabbar" aria-label="{{ __('coach.tabbar.chat') }}">
    <button type="button"
            class="tool-tab {{ $activeTool === null ? 'is-active' : '' }}"
            :class="! railOpen ? 'is-active' : ''"
            @click="toolClose()">
        <svg class="tool-tab-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>{{ __('coach.tabbar.chat') }}</span>
    </button>

    @if ($primary)
        <button type="button"
                class="tool-tab {{ $activeTool === $primary['key'] ? 'is-active' : '' }}"
                @click="toolOpen('{{ $primary['key'] }}')">
            @svg($primary['icon'], 'tool-tab-icon')
            <span>{{ $primary['label'] }}</span>
        </button>
    @else
        <div class="tool-tab is-disabled" aria-hidden="true">
            <svg class="tool-tab-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>—</span>
        </div>
    @endif

    <button type="button"
            class="tool-tab {{ $moreActive ? 'is-active' : '' }}"
            @click="moreOpen = true">
        <svg class="tool-tab-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
        <span>{{ __('coach.tabbar.more') }}</span>
    </button>
</nav>
