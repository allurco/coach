<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The plan starts empty. Two ways to populate it:
 *
 *   A. Talk to the Coach at /coach — onboarding mode interviews you and
 *      creates actions via the CreateAction tool.
 *
 *   B. Paste the contents of database/seeds/plan.json (your backup) into
 *      the Coach chat. The agent will iterate and create each action.
 *
 * The plan.json file is gitignored — it's your private source of truth that
 * you can carry between machines, restore from, or share with the Coach
 * to bulk-recreate your plan.
 */
class InitialPlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info(
            'Plan starts empty by design. Open /coach and tell the Coach about your '
            .'situation, or paste database/seeds/plan.json into the chat to bulk-import.'
        );
    }
}
