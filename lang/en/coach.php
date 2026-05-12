<?php

return [
    'nav' => [
        'coach' => 'Coach',
    ],

    'greeting_first' => 'Hey, :name! What are you looking for a coach for today?',
    'greeting_first_anon' => 'Hey! What are you looking for a coach for today?',
    'greeting_second' => 'You talk, I push back, I remember. Works for money, health, fitness, learning, side projects — or just thinking out loud. Your data stays in your account.',

    'welcome' => [
        'how_label' => 'How it works',
        'concepts' => [
            ['icon' => '🎯', 'title' => 'Goals', 'body' => 'Each focus area becomes a workspace in the sidebar. The agent specializes for it.'],
            ['icon' => '📋', 'title' => 'Plan', 'body' => 'The concrete actions we decide together — with deadlines, priorities, and follow-up.'],
            ['icon' => '🧠', 'title' => 'Memory', 'body' => 'I remember facts you consolidated in past conversations, no need to re-attach.'],
        ],
    ],

    'suggestions' => [
        ['label' => 'I need clarity', 'prompt' => "I'm a bit lost and need clarity on what's going on in my life."],
        ['label' => 'I want to make a change', 'prompt' => 'I want to change something important in my life and I don\'t know where to start.'],
        ['label' => 'I have a goal', 'prompt' => 'I have a big goal to reach and I want to map out a plan.'],
        ['label' => "I'm stuck", 'prompt' => "I've been stuck for a while and want to break through."],
    ],

    'suggestions_first' => [
        ['label' => '🏦 sort out my finances', 'prompt' => 'I want to sort out my finances. Can you interview me to understand my situation and help me build a plan?'],
        ['label' => '🏃 start a health routine', 'prompt' => 'I want to start a health/fitness routine. Help me figure out where to begin.'],
        ['label' => '📚 structure my learning', 'prompt' => 'I want to learn something new in a structured way. Help me map a path.'],
        ['label' => '🧠 think out loud', 'prompt' => 'I need to think out loud about something difficult. Listen and help me organize my thoughts.'],
    ],

    'suggestions_active' => [
        ['label' => "what's my status?", 'prompt' => "What's my situation today? Give me a quick summary of my plan."],
        ['label' => 'anything overdue?', 'prompt' => 'Is anything overdue or about to be? Focus on what matters most.'],
        ['label' => 'most urgent?', 'prompt' => 'What\'s the most urgent thing I should tackle right now?'],
        ['label' => 'progress check', 'prompt' => "Quick recap: what I've moved, what's stuck, what's coming."],
    ],

    'composer' => [
        'placeholder' => 'say something',
        'attach' => 'Attach file',
    ],

    'conversations' => [
        'untitled' => 'untitled',
    ],

    'sidebar' => [
        'title' => 'Goals',
        'new' => 'new',
        'new_goal' => 'Create new goal',
        'empty' => 'No goals yet. Create one to start.',
        'no_activity' => 'no conversation yet',
    ],

    'header' => [
        'new_thread' => 'new thread',
        'history' => 'history',
    ],

    'new_goal_modal' => [
        'title' => 'New goal',
        'name_label' => 'Name',
        'name_placeholder' => 'Eg: Climb out of debt, Half marathon, Learn German',
        'label_label' => 'Category',
        'cancel' => 'Cancel',
        'create' => 'Create goal',
    ],

    'history_panel' => [
        'title' => 'Conversation history',
        'empty' => 'No prior conversations in this goal.',
    ],

    'plan' => [
        'filters' => [
            'pendente' => 'Pending',
            'em_andamento' => 'In progress',
            'concluido' => 'Done',
            'todas' => 'All',
        ],
        'empty' => 'No :status actions.',
        'empty_pendente' => 'Nothing pending. Breathe.',
        'empty_em_andamento' => 'Nothing in progress.',
        'empty_concluido' => 'Nothing finished yet — let’s get started.',
        'empty_todas' => 'No actions in this goal yet. Ask the coach to create the first one.',
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
        'MoveAction' => 'moving action between goals',
        'CreateGoal' => 'creating goal',
        'SwitchToGoal' => 'switching workspace',
        'BudgetSnapshot' => 'building budget plan',
        'ReadBudget' => 'reading current budget',
        'LogWhy' => 'saving the why',
        'LogWorry' => 'logging worry',
        'RememberFact' => 'saving to memory',
        'RecallFacts' => 'reading memory',
        'ShareViaEmail' => 'sending email',
        'WebSearch' => 'searching the web',
        'WebFetch' => 'reading page',
    ],

    'budget_reminder' => [
        'subject_recurring' => 'Budget time — let’s update?',
        'subject_intro' => 'Have you tried Budget Planning yet?',
    ],

    'errors' => [
        'no_text_returned' => '_(coach processed but returned no text — try asking again)_',
        'prefix' => 'Error: ',
        'truncated_warning' => '_(response cut off mid-thought — try again)_',
        'narrated_no_tool' => '_(the coach said it did this but did not run the tool — say "do it again" to retry)_',
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

    'tips' => [
        'dismiss_label' => 'Dismiss tip',
        'pick_focus_area' => [
            'title' => 'Which area do you want to focus on first?',
            'prompt' => 'Which area should I start with?',
        ],
        'set_up_budget' => [
            'title' => 'Set up your monthly budget',
            'prompt' => "Let's build my budget for this month.",
        ],
        'refresh_budget' => [
            'title' => 'Refresh this month’s budget',
            'prompt' => "Let's refresh the budget for this month.",
        ],
        'add_first_action' => [
            'title' => 'Pin down the first concrete step',
            'prompt' => 'Help me set the first concrete action for this goal.',
        ],
        'review_overdue' => [
            'title' => 'You have an overdue action',
            'prompt' => 'Anything overdue? Let’s tackle or push it.',
        ],
        'log_first_win' => [
            'title' => 'Log how that one went',
            'prompt' => 'I completed an action — I want to log how it went.',
        ],
        'trim_heavy_plan' => [
            'title' => 'Plan is heavy — want to trim it?',
            'prompt' => 'I think I have too many actions open. Help me trim it.',
        ],
        'add_second_goal' => [
            'title' => 'Open a second focus area',
            'prompt' => 'I want to open a second goal for another area of life.',
        ],
        'revisit_dormant_goal' => [
            'title' => 'This goal has gone quiet',
            'prompt' => 'This goal has been quiet — help me get unstuck.',
        ],
        'log_the_why' => [
            'title' => 'Why does this goal matter to you?',
            'prompt' => 'I want to log why this goal matters to me.',
        ],
        'revisit_worry' => [
            'title' => 'That worry — did it materialize?',
            'prompt' => 'Let’s revisit that worry I logged — did it materialize?',
        ],
        'save_contact' => [
            'title' => 'Save a contact to share things with later',
            'prompt' => 'I want to save a contact (accountant, partner) so I can share stuff later.',
        ],
        'share_plan' => [
            'title' => 'Email your plan to someone',
            'prompt' => 'I want to email my plan to someone.',
        ],
    ],

    'goal_context' => [
        'empty' => 'The user has no focus set yet (no focus defined). Before creating actions or giving specific advice, ask which area they want to tackle first — can be finance, legal, emotional, health, fitness, learning, or other. Save the answer with RememberFact(kind="goal", label="<area>", summary="<what they want to work on>").',
        'header' => 'Active focus area(s) for the user (specializations that should guide your replies):',
    ],

    'placeholders' => [
        'budget_missing' => '_(snapshot unavailable)_',
        'plan_empty' => '_(plan empty — no actions in flight)_',
        'plan_header' => '**📋 Current plan**',
    ],

    'share' => [
        'default_subject' => 'Shared from my Coach',
        'success' => 'Sent to :email (with a copy to you).',
        'errors' => [
            'unauthenticated' => 'Error: user not authenticated.',
            'empty_body' => 'Error: email body cannot be empty.',
            'unknown_recipient' => 'Error: ":value" is not a valid email nor a saved contact.',
            'rate_limited' => 'Send limit reached — try again in :minutes minute(s).',
        ],
    ],

    'budget' => [
        'auto_close_note' => 'Closed automatically when snapshot #:snapshot_id was generated.',
    ],

    'budget_flyout' => [
        'toggle' => 'Budget',
        'title' => 'Current budget',
        'subtitle' => 'Month :month',
        'net_income' => 'Net income',
        'fixed_costs' => 'Fixed costs',
        'investments' => 'Investments',
        'savings' => 'Savings',
        'leisure' => 'Leisure (leftover)',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'total_with_buffer' => 'Total with 15% buffer',
        'empty_bucket' => '_(no lines)_',
        'deficit_warning' => 'Heads up: shortfall of :amount — planned buckets exceed income.',
        'line_label_placeholder' => 'description',
        'add_line' => 'add line',
        'remove_line' => 'Remove line',
        'save' => 'Save',
        'saved' => 'Budget saved.',
        'share' => 'Share',
        'share_modal_title' => 'Share this budget',
        'share_subject_default' => 'My budget for :month',
        'share_body_default' => "Hi,\n\nHere's my current budget:\n\n{{budget:current}}\n\nLet me know if you have questions.",
        'share_recipient_label' => 'To',
        'share_recipient_placeholder' => 'email or saved contact name',
        'share_subject_label' => 'Subject',
        'share_body_label' => 'Message',
        'share_send' => 'Send',
        'share_cancel' => 'Cancel',
    ],

    'read_budget' => [
        'unauthenticated' => 'Error: user not authenticated.',
        'none' => 'No budget yet — you have never run BudgetSnapshot. To create one we need your net income + a list of fixed costs.',
    ],

    'life_context' => [
        'header' => 'Life context (cuts across every goal — use it to inform advice in any area):',
        'budget' => [
            'none' => 'Finance: no budget set yet.',
            'surplus' => 'Finance: budget for :month with a monthly slack of :amount.',
            'deficit' => 'Finance: budget for :month with a monthly shortfall of :amount.',
            'balanced' => 'Finance: budget for :month with income matching planned spend.',
        ],
        'tool_hint' => 'Whenever the conversation touches big numbers or financial commitments (even outside the finance goal), call BudgetSnapshot for detail before advising. If a budget already exists (the line above tells you which month), DO NOT ask the user to create a new one from scratch — call ReadBudget to pull what exists.',
    ],

    'share_modal' => [
        'icon_label' => 'Share via email',
        'title' => 'Share this message',
        'default_subject' => 'Coach summary — :date',
        'recipient_label' => 'To',
        'recipient_placeholder' => 'email or saved contact name',
        'subject_label' => 'Subject',
        'body_label' => 'Message',
        'send' => 'Send',
        'cancel' => 'Cancel',
    ],

    'specializations' => [
        'finance' => "FINANCE: Focus on cash flow, debts vs reserves, business/personal separation when relevant, and net worth goals. Do real math with real numbers. NEVER give specific tax advice — always refer to an accountant for regulatory questions.\n\nBudget Planning (4 buckets): when the user interviews you with income + expenses OR asks for a financial plan, use the **BudgetSnapshot** tool. It splits net income into four buckets:\n  1. Fixed Costs (target 50-60%): rent, utilities, groceries, insurance, transport, debts, subscriptions. Applies an automatic 15% buffer for forgotten line items.\n  2. Investments (target 10%): retirement, stocks, long-term.\n  3. Savings (target 5-10%): emergency fund, vacation, specific goals.\n  4. Leisure (target 20-35%): the LEFTOVER — `net income - fixed - investments - savings`. Don't budget upfront; compute.\n\nRecommended flow: ask for net income + list fixed costs line by line + investments + savings. When the user names a month ('June plan', 'for July'), pass `month` as `YYYY-MM` or `MM/YYYY` — don't leave it blank, or the snapshot defaults to the current month. Call BudgetSnapshot with the breakdown. The tool's table is rendered into the chat automatically — **do not repeat the numbers or rewrite the table**. After the call, comment in 1-2 sentences on which bucket is off-target and propose ONE concrete change. The tool output is the snapshot of record; your sentences are the coaching on top of it.",

        'legal' => 'LEGAL: When the topic is contractual/tax/regulatory, remind the user to consult a lawyer for specific recommendations. You can discuss general concepts and help organize questions for the professional, but you do not replace legal counsel.',

        'emotional' => 'EMOTIONAL: Use genuine empathy, validate feelings before proposing practical solutions. Avoid minimizing ("it\'ll be fine") or rushing. For crises (self-harm, suicidal ideation), always redirect to professional services — in the US, 988 (Suicide & Crisis Lifeline).',

        'health' => 'HEALTH: General health discussion is organizational (appointments, tests, habits). For any pain, new symptom, or diagnosis, refer the user to a doctor or health professional — never interpret symptoms or suggest medication.',

        'fitness' => 'FITNESS: Structure training around consistency first, intensity second. Small sustainable gains beat peaks. For joint pain, injury, or starting a heavier program, always refer to a professional (trainer, physical therapist).',

        'learning' => 'LEARNING: Learning works with progressive practice and spaced repetition. Help structure small, measurable goals with frequent feedback. Acknowledge concrete wins. Apply the 80/20 rule: what yields the most return per hour invested.',
    ],
];
