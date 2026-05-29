{{-- "Mais" sheet — the Workspace's non-primary Tools (Plan, Contacts, and
     any future pack Tools), opened from the tab bar. Bottom sheet on mobile.
     Alpine `moreOpen` drives it; selecting a Tool opens it in the rail.
     ADR 0007. --}}
<div class="more-scrim" x-show="moreOpen" x-transition.opacity.duration.200ms @click="moreOpen = false" style="display:none"></div>

<div class="more-sheet" :class="moreOpen ? 'is-open' : ''">
    <div class="more-sheet-handle" @click="moreOpen = false"></div>
    <div class="more-sheet-title">{{ __('coach.tabbar.more') }}</div>
    <div class="more-grid">
        @foreach ($this->workspaceTools() as $tool)
            @continue($tool['is_primary'])
            <button type="button"
                    class="more-item"
                    wire:click="openTool('{{ $tool['key'] }}')"
                    @click="moreOpen = false">
                <span class="more-item-icon">@svg($tool['icon'], 'more-svg')</span>
                <span class="more-item-label">{{ $tool['label'] }}</span>
            </button>
        @endforeach
    </div>
</div>
