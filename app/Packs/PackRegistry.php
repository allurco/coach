<?php

namespace App\Packs;

/**
 * Runtime registry of enabled Domain Packs. Each enabled pack registers
 * itself here during its boot() so the rest of the app (most notably
 * the Agent's prompt builder) can iterate all packs and collect signals
 * without knowing which ones are present.
 *
 * Pack identity is the label string — same value used in `Goal.label`.
 * See ADR 0001.
 */
class PackRegistry
{
    /** @var array<string, DomainPack> */
    private array $packs = [];

    public function add(DomainPack $pack): void
    {
        $this->packs[$pack->label()] = $pack;
    }

    /** @return array<string, DomainPack> */
    public function enabled(): array
    {
        return $this->packs;
    }

    public function get(string $label): ?DomainPack
    {
        return $this->packs[$label] ?? null;
    }
}
