<?php

namespace App\Agent\Tools;

/**
 * Tags an agent tool whose text output the chat should treat as verbatim
 * Markdown — i.e. the raw text is preserved in the persisted assistant
 * message so the structured display (e.g. a budget snapshot table)
 * survives a page reload.
 *
 * Packs implement this on the relevant Tool class instead of having
 * CoachInteraction hard-code their class name. See ADR 0006 (packs
 * self-register into core catalogs).
 */
interface VerbatimOutput {}
