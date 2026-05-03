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
];
