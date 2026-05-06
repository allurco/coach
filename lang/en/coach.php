<?php

return [
    'nav' => [
        'coach' => 'Coach',
    ],

    'greeting_first' => 'Hey! What are you looking for a coach for today?',
    'greeting_second' => 'How can I help you be the best version of yourself?',

    'suggestions' => [
        ['label' => 'I need clarity', 'prompt' => "I'm a bit lost and need clarity on what's going on in my life."],
        ['label' => 'I want to make a change', 'prompt' => 'I want to change something important in my life and I don\'t know where to start.'],
        ['label' => 'I have a goal', 'prompt' => 'I have a big goal to reach and I want to map out a plan.'],
        ['label' => "I'm stuck", 'prompt' => "I've been stuck for a while and want to break through."],
    ],

    'suggestions_active' => [
        ['label' => "what's my status?", 'prompt' => "What's my situation today? Give me a quick summary of my plan."],
        ['label' => 'anything overdue?', 'prompt' => 'Is anything overdue or about to be? Focus on what matters most.'],
        ['label' => 'most urgent?', 'prompt' => 'What\'s the most urgent thing I should tackle right now?'],
        ['label' => 'progress check', 'prompt' => "Quick recap: what I've moved, what's stuck, what's coming."],
    ],

    'composer' => [
        'placeholder' => 'say something',
    ],

    'conversations' => [
        'untitled' => 'untitled',
    ],

    'plan' => [
        'filters' => [
            'pendente' => 'Pending',
            'em_andamento' => 'In progress',
            'concluido' => 'Done',
            'todas' => 'All',
        ],
        'empty' => 'No :status actions.',
        'view_all' => 'View all →',
        'mark_done' => 'Mark done',
        'snooze' => 'Snooze',
        'snooze_options' => [
            'tomorrow' => 'Tomorrow',
            '3days' => '3 days',
            'week' => 'Next week',
            'month' => '1 month',
        ],
        'count' => '{0}no actions|{1}1 action|[2,*]:count actions',
        'details' => [
            'expand' => 'Show details',
            'collapse' => 'Hide',
            'description' => 'Description',
            'importance' => 'Importance',
            'difficulty' => 'Difficulty',
            'snoozed_until' => 'Snoozed until :date',
            'result_notes' => 'Completion notes',
            'completed_at' => 'Completed on :date',
            'attachments' => 'Attachments',
            'no_attachments' => 'No attachments',
        ],
    ],

    'complete_modal' => [
        'title' => 'Complete action',
        'label' => 'How did you complete it?',
        'optional' => '(optional)',
        'placeholder' => 'Eg: Paid via wire transfer from savings. Cleared the whole balance.',
        'cancel' => 'Cancel',
        'confirm' => 'Complete',
    ],

    'tool_labels' => [
        'ListActions' => 'reading plan',
        'CreateAction' => 'creating action',
        'UpdateAction' => 'updating action',
        'RememberFact' => 'saving to memory',
        'RecallFacts' => 'reading memory',
        'WebSearch' => 'searching the web',
        'WebFetch' => 'reading page',
    ],

    'errors' => [
        'no_text_returned' => '_(coach processed but returned no text — try asking again)_',
        'prefix' => 'Error: ',
    ],

    'recap' => [
        'done' => 'Done.',
        'with_results' => 'Done — :parts. Open the plan in the flyout to verify.',
        'created_one' => 'created 1 action',
        'created_many' => 'created :count actions',
        'updated_one' => 'updated 1 action',
        'updated_many' => 'updated :count actions',
        'remembered_one' => 'saved 1 fact',
        'remembered_many' => 'saved :count facts',
    ],

    'attachments' => [
        'analyze_default' => 'Analyze the attached file(s).',
        'sent_indicator' => '(attachment sent)',
    ],

    'goal_context' => [
        'empty' => 'The user has no focus set yet (no focus defined). Before creating actions or giving specific advice, ask which area they want to tackle first — can be finance, legal, emotional, health, fitness, learning, or other. Save the answer with RememberFact(kind="goal", label="<area>", summary="<what they want to work on>").',
        'header' => 'Active focus area(s) for the user (specializations that should guide your replies):',
    ],

    'specializations' => [
        'finance' => 'FINANCE: Focus on cash flow, debts vs reserves, business/personal separation when relevant, and net worth goals. Do real math with real numbers. NEVER give specific tax advice — always refer to an accountant for regulatory questions.',

        'legal' => 'LEGAL: When the topic is contractual/tax/regulatory, remind the user to consult a lawyer for specific recommendations. You can discuss general concepts and help organize questions for the professional, but you do not replace legal counsel.',

        'emotional' => 'EMOTIONAL: Use genuine empathy, validate feelings before proposing practical solutions. Avoid minimizing ("it\'ll be fine") or rushing. For crises (self-harm, suicidal ideation), always redirect to professional services — in the US, 988 (Suicide & Crisis Lifeline).',

        'health' => 'HEALTH: General health discussion is organizational (appointments, tests, habits). For any pain, new symptom, or diagnosis, refer the user to a doctor or health professional — never interpret symptoms or suggest medication.',

        'fitness' => 'FITNESS: Structure training around consistency first, intensity second. Small sustainable gains beat peaks. For joint pain, injury, or starting a heavier program, always refer to a professional (trainer, physical therapist).',

        'learning' => 'LEARNING: Learning works with progressive practice and spaced repetition. Help structure small, measurable goals with frequent feedback. Acknowledge concrete wins. Apply the 80/20 rule: what yields the most return per hour invested.',
    ],
];
