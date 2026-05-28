<?php

namespace App\Tools;

/**
 * A workspace UI surface the user opens — the Budget planner, the Plan,
 * the Contacts manager (see CONTEXT.md "Tool"). Distinct from an Agent
 * Tool (an invisible LLM-callable function in App\Agent\Tools).
 *
 * A Tool is described by a small value object and rendered as a
 * self-contained Livewire component, so the Workspace can embed it on
 * mobile (tab bar) and desktop (right rail) alike.
 */
class Tool
{
    /**
     * @param  string  $key  Stable slug — used as the `?tool=` URL value and tab id.
     * @param  string  $label  Translation key for the tab label (rendered via __()).
     * @param  string  $icon  Heroicon name (e.g. heroicon-o-wallet).
     * @param  string  $component  Livewire component alias the Workspace mounts.
     * @param  bool  $isPrimary  Whether this is the pack's primary Tool (the slot next
     *                           to Chat in the tab bar). Core tools are never primary.
     * @param  string  $scope  'core' (every Goal) or a pack label like 'finance'
     *                         (only that pack's Goals).
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $component,
        public bool $isPrimary = false,
        public string $scope = 'core',
    ) {}

    public function isCore(): bool
    {
        return $this->scope === 'core';
    }

    /**
     * Visible for a Goal carrying the given pack label: core Tools always
     * show; pack Tools only on their own pack's Goals.
     */
    public function appliesTo(string $goalLabel): bool
    {
        return $this->isCore() || $this->scope === $goalLabel;
    }
}
