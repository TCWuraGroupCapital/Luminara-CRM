<?php

namespace Webkul\Agent\Actions\Intake;

use Illuminate\Support\Facades\Event;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Agent\Actions\Contacts\ContactAction;
use Webkul\Agent\Actions\Leads\LeadAction;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;

class IntakeCapture extends LeadAction
{
    private const FREE_EMAIL_DOMAINS = [
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'outlook.com',
        'hotmail.com',
        'icloud.com',
        'proton.me',
        'protonmail.com',
        'example.com',
        'example.org',
        'example.net',
        'intake.luminara.invalid',
    ];

    public function __construct(
        LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
        protected ActivityRepository $activityRepository,
    ) {
        parent::__construct($leadRepository);
    }

    public function name(): string
    {
        return 'intake_capture';
    }

    public function summary(): string
    {
        return 'Deterministically extract contact, company, phone, value, and deal title from raw forwarded text, upsert the person in scope, create a Stage 1 lead, and attach the raw note.';
    }

    public function permission(): string
    {
        return 'leads.create';
    }

    public function rules(): array
    {
        return [
            'raw_text' => ['required', 'string', 'min:3', 'max:10000'],
            'source_channel' => ['nullable', 'string', 'max:64'],
            'default_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function estimatedValue(array $params): ?float
    {
        $parsed = $this->parseRawText((string) $params['raw_text'], isset($params['default_value']) ? (float) $params['default_value'] : null);

        return $parsed['lead_value'] ?? 0.0;
    }

    public function handle(array $params): array
    {
        $rawText = trim((string) $params['raw_text']);
        $defaultValue = isset($params['default_value']) ? (float) $params['default_value'] : null;
        $parsed = $this->parseRawText($rawText, $defaultValue);
        $userId = auth()->guard('user')->id();

        $person = ContactAction::findScopedByEmail($this->personRepository, $parsed['email']);

        if (! $person instanceof Person) {
            $organizationId = null;

            if ($parsed['company_name'] !== null) {
                /** @var Organization|null $existingOrg */
                $existingOrg = bouncer()
                    ->scopeToAuthorizedUsers($this->organizationRepository->getModel()->newQuery())
                    ->where('name', $parsed['company_name'])
                    ->first();

                if ($existingOrg instanceof Organization) {
                    $organizationId = $existingOrg->getKey();
                } else {
                    Event::dispatch('contacts.organization.create.before');

                    /** @var Organization $createdOrg */
                    $createdOrg = $this->organizationRepository->create([
                        'entity_type' => 'organizations',
                        'name' => $parsed['company_name'],
                        'user_id' => $userId,
                    ]);

                    Event::dispatch('contacts.organization.create.after', $createdOrg);

                    $organizationId = $createdOrg->getKey();
                }
            }

            Event::dispatch('contacts.person.create.before');

            /** @var Person $person */
            $person = $this->personRepository->create(array_filter([
                'entity_type' => 'persons',
                'name' => $parsed['person_name'],
                'emails' => [['value' => $parsed['email'], 'label' => 'work']],
                'contact_numbers' => $parsed['phone'] !== null
                    ? [['value' => $parsed['phone'], 'label' => 'work']]
                    : null,
                'organization_id' => $organizationId,
                'user_id' => $userId,
            ], fn ($v) => $v !== null));

            Event::dispatch('contacts.person.create.after', $person);
        }

        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelineRepository->getDefaultPipeline();

        Event::dispatch('lead.create.before');

        /** @var Lead $lead */
        $lead = $this->leadRepository->create([
            'entity_type' => 'leads',
            'status' => 1,
            'title' => $parsed['deal_title'],
            'description' => mb_substr($rawText, 0, 5000),
            'lead_value' => $parsed['lead_value'],
            'user_id' => $userId,
            'person' => ['id' => $person->getKey()],
            'lead_source_id' => $this->sourceRepository->getModel()->newQuery()->firstOrFail()->getKey(),
            'lead_type_id' => $this->typeRepository->getModel()->newQuery()->firstOrFail()->getKey(),
            'lead_pipeline_id' => $pipeline->getKey(),
            'lead_pipeline_stage_id' => $pipeline->stages()->firstOrFail()->getKey(),
        ]);

        Event::dispatch('lead.create.after', $lead);

        $channel = ! empty($params['source_channel']) ? " ({$params['source_channel']})" : '';

        Event::dispatch('activity.create.before');

        /** @var Activity $activity */
        $activity = $this->activityRepository->create([
            'type' => 'note',
            'title' => 'Inbound intake'.$channel,
            'comment' => $rawText,
            'is_done' => 1,
            'user_id' => $userId,
        ]);

        $lead->activities()->syncWithoutDetaching([$activity->getKey()]);
        $person->activities()->syncWithoutDetaching([$activity->getKey()]);

        Event::dispatch('activity.create.after', $activity);

        return [
            'person' => [
                'id' => $person->getKey(),
                'name' => $person->getAttribute('name'),
                'email' => $parsed['email'],
                'phone' => $parsed['phone'],
                'organization_id' => $person->getAttribute('organization_id'),
            ],
            'lead' => $this->present($lead->refresh()),
            'activity_id' => $activity->getKey(),
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array{email: string, phone: ?string, person_name: string, company_name: ?string, deal_title: string, lead_value: ?float}
     */
    private function parseRawText(string $rawText, ?float $defaultValue): array
    {
        $email = null;

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $rawText, $emailMatch)) {
            $email = strtolower($emailMatch[0]);
        }

        $synthesizedEmail = $email ?? 'intake+'.substr(hash('sha256', $rawText), 0, 10).'@intake.luminara.invalid';

        $phone = null;

        if (preg_match('/(?:\+?\d[\d\s().\-]{6,}\d)/', $rawText, $phoneMatch)) {
            $digits = preg_replace('/\D+/', '', $phoneMatch[0]) ?? '';

            if (strlen($digits) >= 7 && strlen($digits) <= 16) {
                $phone = trim($phoneMatch[0]);
            }
        }

        $personName = null;

        if (preg_match('/^From:\s*"?([^"<\r\n]+?)"?\s*<[^>]+>/mi', $rawText, $fromMatch)) {
            $personName = trim($fromMatch[1]);
        } elseif (preg_match('/^(?:Name|Contact):\s*([^\r\n]+)/mi', $rawText, $nameMatch)) {
            $personName = trim($nameMatch[1]);
        } elseif (preg_match('/\b(?:I am|I\'m|This is)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})\b/', $rawText, $introMatch)) {
            $personName = trim($introMatch[1]);
        } elseif ($email !== null) {
            $local = preg_replace('/[^a-zA-Z]+/', ' ', (string) strtok($email, '@')) ?? '';
            $derived = ucwords(strtolower(trim($local)));

            if ($derived !== '') {
                $personName = $derived;
            }
        }

        $personName = $personName !== null && $personName !== '' ? mb_substr($personName, 0, 120) : 'Inbound Contact';

        $companyName = null;

        if (preg_match('/^(?:Company|Organization|Org):\s*([^\r\n]+)/mi', $rawText, $orgMatch)) {
            $companyName = trim($orgMatch[1]);
        } elseif (preg_match('/\b(?:from|at)\s+([A-Z][A-Za-z0-9&.\-]*(?:\s+[A-Z][A-Za-z0-9&.\-]*){0,3})(?=[,.\r\n]|\s+(?:and|looking|interested|asking|about|to)\b)/', $rawText, $atMatch)) {
            $companyName = trim($atMatch[1]);
        } elseif ($email !== null) {
            $domain = strtolower((string) substr((string) strrchr($email, '@'), 1));

            if ($domain !== '' && ! in_array($domain, self::FREE_EMAIL_DOMAINS, true)) {
                $root = explode('.', $domain)[0];
                $companyName = ucfirst($root);
            }
        }

        if ($companyName !== null) {
            $companyName = mb_substr($companyName, 0, 120);
        }

        $dealTitle = null;

        if (preg_match('/^Subject:\s*(?:(?:Re|Fwd|Fw):\s*)*([^\r\n]+)/mi', $rawText, $subjectMatch)) {
            $dealTitle = trim($subjectMatch[1]);
        }

        if ($dealTitle === null || $dealTitle === '') {
            $dealTitle = $companyName !== null
                ? "{$companyName} — {$personName}"
                : "{$personName} — Inbound Inquiry";
        }

        $leadValue = $defaultValue;

        if (preg_match('/(?:\$|USD\s*)([\d,]+(?:\.\d{1,2})?)\s*([kKmM])?\b/', $rawText, $valMatch)) {
            $base = (float) str_replace(',', '', $valMatch[1]);
            $multiplier = match (strtolower($valMatch[2] ?? '')) {
                'k' => 1000.0,
                'm' => 1000000.0,
                default => 1.0,
            };
            $leadValue = min(99999999.99, round($base * $multiplier, 2));
        }

        return [
            'email' => $synthesizedEmail,
            'phone' => $phone,
            'person_name' => $personName,
            'company_name' => $companyName,
            'deal_title' => mb_substr($dealTitle, 0, 255),
            'lead_value' => $leadValue,
        ];
    }
}
