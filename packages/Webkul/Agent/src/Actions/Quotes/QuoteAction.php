<?php

namespace Webkul\Agent\Actions\Quotes;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;
use Webkul\Quote\Repositories\QuoteRepository;

abstract class QuoteAction extends AgentAction
{
    public function __construct(
        protected QuoteRepository $quoteRepository,
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
    ) {}

    /**
     * @return Builder<Quote>
     */
    protected function scopedQuotes(): Builder
    {
        return bouncer()->scopeToAuthorizedUsers($this->quoteRepository->getModel()->newQuery());
    }

    /**
     * @return Builder<Person>
     */
    protected function scopedPersons(): Builder
    {
        return bouncer()->scopeToAuthorizedUsers($this->personRepository->getModel()->newQuery());
    }

    /**
     * @return Builder<Lead>
     */
    protected function scopedLeads(): Builder
    {
        return bouncer()->scopeToAuthorizedUsers($this->leadRepository->getModel()->newQuery());
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function snapshot(Quote $quote): array
    {
        return [
            'id' => $quote->getKey(),
            'subject' => $quote->getAttribute('subject'),
            'person_id' => $quote->getAttribute('person_id'),
            'sub_total' => ($sub = $quote->getAttribute('sub_total')) !== null ? (float) $sub : 0.0,
            'discount_percent' => ($disc = $quote->getAttribute('discount_percent')) !== null ? (float) $disc : 0.0,
            'grand_total' => ($grand = $quote->getAttribute('grand_total')) !== null ? (float) $grand : 0.0,
            'owner_id' => $quote->getAttribute('user_id'),
            'updated_at' => $quote->getAttribute('updated_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Quote $quote, bool $withItems = false): array
    {
        $personId = $quote->getAttribute('person_id');
        $personName = $quote->relationLoaded('person')
            ? $quote->getRelationValue('person')?->getAttribute('name')
            : ($personId !== null ? Person::query()->whereKey($personId)->value('name') : null);

        $expiredAt = $quote->getAttribute('expired_at');
        $createdAt = $quote->getAttribute('created_at');

        $base = [
            'id' => $quote->getKey(),
            'subject' => $quote->getAttribute('subject'),
            'description' => $quote->getAttribute('description'),
            'person_id' => $personId,
            'person' => $personName,
            'owner_id' => $quote->getAttribute('user_id'),
            'discount_percent' => ($discPct = $quote->getAttribute('discount_percent')) !== null ? (float) $discPct : 0.0,
            'discount_amount' => ($discAmt = $quote->getAttribute('discount_amount')) !== null ? (float) $discAmt : 0.0,
            'tax_amount' => ($taxAmt = $quote->getAttribute('tax_amount')) !== null ? (float) $taxAmt : 0.0,
            'sub_total' => ($sub = $quote->getAttribute('sub_total')) !== null ? (float) $sub : 0.0,
            'grand_total' => ($grand = $quote->getAttribute('grand_total')) !== null ? (float) $grand : 0.0,
            'expired_at' => $expiredAt instanceof DateTimeInterface
                ? $expiredAt->format('Y-m-d')
                : (is_string($expiredAt) && $expiredAt !== '' ? substr($expiredAt, 0, 10) : null),
            'created_at' => $createdAt instanceof CarbonInterface ? $createdAt->toIso8601String() : null,
        ];

        if (! $withItems) {
            return $base;
        }

        $items = QuoteItem::query()
            ->where('quote_id', $quote->getKey())
            ->get()
            ->map(fn (QuoteItem $item): array => [
                'id' => $item->getKey(),
                'product_id' => $item->getAttribute('product_id'),
                'sku' => $item->getAttribute('sku'),
                'name' => $item->getAttribute('name'),
                'quantity' => (int) ($item->getAttribute('quantity') ?? 0),
                'price' => (float) ($item->getAttribute('price') ?? 0),
                'discount_percent' => (float) ($item->getAttribute('discount_percent') ?? 0),
                'discount_amount' => (float) ($item->getAttribute('discount_amount') ?? 0),
                'tax_percent' => (float) ($item->getAttribute('tax_percent') ?? 0),
                'tax_amount' => (float) ($item->getAttribute('tax_amount') ?? 0),
                'total' => (float) ($item->getAttribute('total') ?? 0),
            ])
            ->all();

        return $base + ['items' => $items];
    }
}
