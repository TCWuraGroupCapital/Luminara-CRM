<?php

use Webkul\Agent\Actions\Activities\CompleteActivity;
use Webkul\Agent\Actions\Activities\ListActivities;
use Webkul\Agent\Actions\Activities\LogNote;
use Webkul\Agent\Actions\Activities\ScheduleActivity;
use Webkul\Agent\Actions\Briefing\BriefingToday;
use Webkul\Agent\Actions\Contacts\GetContact;
use Webkul\Agent\Actions\Contacts\MergePersons;
use Webkul\Agent\Actions\Contacts\SearchContacts;
use Webkul\Agent\Actions\Contacts\UpsertOrganization;
use Webkul\Agent\Actions\Contacts\UpsertPerson;
use Webkul\Agent\Actions\Intake\IntakeCapture;
use Webkul\Agent\Actions\Leads\CreateLead;
use Webkul\Agent\Actions\Leads\DeleteLead;
use Webkul\Agent\Actions\Leads\GetLead;
use Webkul\Agent\Actions\Leads\MoveLeadStage;
use Webkul\Agent\Actions\Leads\SearchLeads;
use Webkul\Agent\Actions\Leads\UpdateLead;
use Webkul\Agent\Actions\Ledger\EntityLedgerHistory;
use Webkul\Agent\Actions\Ledger\VerifyLedgerBlock;
use Webkul\Agent\Actions\Memory\ProposePlaybookRule;
use Webkul\Agent\Actions\Memory\ReadPlaybook;
use Webkul\Agent\Actions\Memory\RecallFacts;
use Webkul\Agent\Actions\Memory\RememberFact;
use Webkul\Agent\Actions\Memory\RetractFact;
use Webkul\Agent\Actions\Products\SearchProducts;
use Webkul\Agent\Actions\Quotes\DeleteQuote;
use Webkul\Agent\Actions\Quotes\DraftQuote;
use Webkul\Agent\Actions\Quotes\GetQuote;
use Webkul\Agent\Actions\Quotes\SearchQuotes;

$memoryEnabled = filter_var(env('LUMINARA_AGENT_MEMORY', false), FILTER_VALIDATE_BOOLEAN);

return [
    /**
     * Durable, human-approved customer facts. Off by default: enabling it adds one table and three
     * actions, and changes no existing action, prompt or transport.
     */
    'memory' => [
        'enabled' => $memoryEnabled,
    ],

    /**
     * How long a call may wait for a person before it is closed unrun.
     */
    'approval_ttl_hours' => 72,

    /**
     * The in-app assistant. It calls the same actions, as the signed-in user, through a model on the
     * Anthropic Messages API. `base_url` may point at any service that speaks that API; CRM records
     * the user's role can read are sent to it.
     */
    'assistant' => [
        'api_key' => env('LUMINARA_ASSISTANT_API_KEY'),
        'base_url' => env('LUMINARA_ASSISTANT_BASE_URL'),
        'model' => env('LUMINARA_ASSISTANT_MODEL', 'claude-opus-5-5'),
        'max_steps' => 8,
        'max_turns' => 40,
    ],

    /**
     * Every action an agent can call. Adding a class here is the only step needed to expose it over
     * the token API, the MCP endpoint, the CLI, and the context schema.
     */
    'actions' => [
        SearchLeads::class,
        GetLead::class,
        CreateLead::class,
        MoveLeadStage::class,
        DeleteLead::class,
        UpdateLead::class,
        IntakeCapture::class,
        SearchContacts::class,
        GetContact::class,
        UpsertPerson::class,
        UpsertOrganization::class,
        MergePersons::class,
        SearchProducts::class,
        SearchQuotes::class,
        GetQuote::class,
        DraftQuote::class,
        DeleteQuote::class,
        ListActivities::class,
        LogNote::class,
        ScheduleActivity::class,
        CompleteActivity::class,
        BriefingToday::class,
        ReadPlaybook::class,
        ProposePlaybookRule::class,
        VerifyLedgerBlock::class,
        EntityLedgerHistory::class,
        ...($memoryEnabled ? [RecallFacts::class, RememberFact::class, RetractFact::class] : []),
    ],
];
