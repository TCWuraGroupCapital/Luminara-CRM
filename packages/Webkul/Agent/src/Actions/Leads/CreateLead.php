<?php

namespace Webkul\Agent\Actions\Leads;

use Illuminate\Support\Facades\Event;
use Webkul\Agent\Actions\Contacts\ContactAction;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;

class CreateLead extends LeadAction
{
    public function __construct(
        LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {
        parent::__construct($leadRepository);
    }

    public function name(): string
    {
        return 'leads_create';
    }

    public function summary(): string
    {
        return 'Create a lead owned by the caller in the first stage of the default pipeline. '
            .'Give person_id for an existing contact, or person_name and person_email to create one.';
    }

    public function permission(): string
    {
        return 'leads.create';
    }

    public function estimatedValue(array $params): ?float
    {
        return isset($params['lead_value']) ? (float) $params['lead_value'] : 0.0;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lead_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'person_id' => ['nullable', 'integer', 'min:1', 'required_without:person_name'],
            'person_name' => ['nullable', 'string', 'max:255', 'required_without:person_id'],
            // The CRM requires an email on every contact, and a contact's unique key is built from it.
            'person_email' => ['nullable', 'email', 'max:255', 'required_with:person_name'],
            'lead_source_id' => ['nullable', 'integer', 'exists:lead_sources,id'],
            'lead_type_id' => ['nullable', 'integer', 'exists:lead_types,id'],
        ];
    }

    public function handle(array $params): array
    {
        $userId = auth()->guard('user')->id();

        if (isset($params['person_id'])) {
            $person = ['id' => bouncer()->scopeToAuthorizedUsers($this->personRepository->getModel()->newQuery())
                ->findOrFail($params['person_id'])->getKey()];
        } else {
            $normalizedEmail = isset($params['person_email']) ? strtolower(trim((string) $params['person_email'])) : '';
            $existingPerson = $normalizedEmail !== ''
                ? ContactAction::findScopedByEmail($this->personRepository, $normalizedEmail)
                : null;

            $person = $existingPerson instanceof Person
                ? ['id' => $existingPerson->getKey()]
                : array_filter([
                    'name' => $params['person_name'],
                    'user_id' => $userId,
                    'emails' => $normalizedEmail !== ''
                        ? [['value' => $normalizedEmail, 'label' => 'work']]
                        : null,
                ]);
        }

        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelineRepository->getDefaultPipeline();

        Event::dispatch('lead.create.before');

        /** @var Lead $lead */
        $lead = $this->leadRepository->create([
            'entity_type' => 'leads',
            'status' => 1,
            'title' => $params['title'],
            'description' => $params['description'] ?? null,
            'lead_value' => $params['lead_value'] ?? null,
            'user_id' => $userId,
            'person' => $person,
            'lead_source_id' => $params['lead_source_id'] ?? $this->sourceRepository->getModel()->newQuery()->firstOrFail()->getKey(),
            'lead_type_id' => $params['lead_type_id'] ?? $this->typeRepository->getModel()->newQuery()->firstOrFail()->getKey(),
            'lead_pipeline_id' => $pipeline->getKey(),
            'lead_pipeline_stage_id' => $pipeline->stages()->firstOrFail()->getKey(),
        ]);

        Event::dispatch('lead.create.after', $lead);

        return ['lead' => $this->present($lead->refresh())];
    }
}
