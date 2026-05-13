<?php

use App\Ai\Agents\CoachAgent;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('declares items on every array schema property across every registered tool', function () {
    // Gemini rejects function declarations whose array params don't carry
    // an `items` schema (HTTP 400 INVALID_ARGUMENT). One missing field
    // breaks every chat message until rolled back. Walk every tool the
    // agent registers, serialize each property, and fail the suite if
    // any array slot ships without items.
    $factory = new JsonSchemaTypeFactory;
    $agent = (new CoachAgent)->forUser($this->user);

    $offenders = [];
    foreach ($agent->tools() as $tool) {
        foreach ($tool->schema($factory) as $propertyName => $type) {
            if (! $type instanceof Type) {
                continue;
            }
            $serialized = $type->toArray();
            if (($serialized['type'] ?? null) === 'array' && ! isset($serialized['items'])) {
                $offenders[] = $tool::class.'::'.$propertyName;
            }
        }
    }

    expect($offenders)->toBe([], "Tools with array params missing 'items': ".implode(', ', $offenders));
});
