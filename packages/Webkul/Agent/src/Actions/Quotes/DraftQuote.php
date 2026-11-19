<?php

namespace Webkul\Agent\Actions\Quotes;

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Validator;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Repositories\QuoteRepository;

class DraftQuote extends QuoteAction
{
    public const DISCOUNT_APPROVAL_THRESHOLD = 15.0;

    public function __construct(
        QuoteRepository $quoteRepository,
        PersonRepository $personRepository,
        LeadRepository $leadRepository,
        protected ProductRepository $productRepository,
    ) {
        parent::__construct($quoteRepository, $personRepository, $leadRepository);
    }

    public function name(): string
    {
        return 'quotes_draft';
    }

    public function summary(): string
    {
        return 'Create an itemized quote for a contact (and optional lead). Discounts above 15% wait for human approval.';
    }

    public function permission(): string
    {
        return 'quotes.create';
    }

    public function approval(): string
    {
        return self::APPROVAL_CONDITIONAL;
    }

    public function requiresApproval(array $params): bool
    {
        if ((float) ($params['discount_percent'] ?? 0) > self::DISCOUNT_APPROVAL_THRESHOLD) {
            return true;
        }

        foreach ($params['items'] ?? [] as $item) {
            if (is_array($item) && (float) ($item['discount_percent'] ?? 0) > self::DISCOUNT_APPROVAL_THRESHOLD) {
                return true;
            }
        }

        $totals = $this->computeTotals($params);

        if ($totals['catalog_total'] > 0) {
            $effectiveNet = $totals['sub_total'] - $totals['discount_amount'];
            $effectiveMarkdownPct = (($totals['catalog_total'] - $effectiveNet) / $totals['catalog_total']) * 100.0;

            if ($effectiveMarkdownPct > self::DISCOUNT_APPROVAL_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    public function estimatedValue(array $params): ?float
    {
        $totals = $this->computeTotals($params);

        return max($totals['catalog_total'], $totals['grand_total']);
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'person_id' => ['required', 'integer', 'min:1'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'expired_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function afterValidate(Validator $validator, array $params): void
    {
        $totals = $this->computeTotals($params);

        if ($totals['sub_total'] > 99999999.99 || $totals['grand_total'] > 99999999.99) {
            $validator->errors()->add('items', 'Quote total cannot exceed 99,999,999.99.');
        }
    }

    public function subject(array $params): array
    {
        $person = $this->scopedPersons()->whereKey($params['person_id'])->firstOrFail();

        $rows = [[
            'entity' => 'person',
            'id' => $person->getKey(),
            'name' => $person->getAttribute('name'),
            'updated_at' => $person->getAttribute('updated_at')?->toIso8601String(),
        ]];

        if (isset($params['lead_id'])) {
            $lead = $this->scopedLeads()->whereKey($params['lead_id'])->firstOrFail();

            $rows[] = [
                'entity' => 'lead',
                'id' => $lead->getKey(),
                'title' => $lead->getAttribute('title'),
                'updated_at' => $lead->getAttribute('updated_at')?->toIso8601String(),
            ];
        }

        return $rows;
    }

    public function handle(array $params): array
    {
        $person = $this->scopedPersons()->whereKey($params['person_id'])->firstOrFail();
        $lead = isset($params['lead_id'])
            ? $this->scopedLeads()->whereKey($params['lead_id'])->firstOrFail()
            : null;

        $totals = $this->computeTotals($params);

        Event::dispatch('quote.create.before');

        /** @var Quote $quote */
        $quote = $this->quoteRepository->create([
            'entity_type' => 'quotes',
            'subject' => $params['subject'],
            'description' => $params['description'] ?? null,
            'person_id' => $person->getKey(),
            'lead_id' => $lead?->getKey(),
            'user_id' => auth()->guard('user')->id(),
            'expired_at' => $params['expired_at'] ?? now()->addDays(30)->toDateString(),
            'discount_percent' => $totals['discount_percent'],
            'discount_amount' => $totals['discount_amount'],
            'tax_amount' => $totals['tax_amount'],
            'adjustment_amount' => 0,
            'sub_total' => $totals['sub_total'],
            'grand_total' => $totals['grand_total'],
            'items' => $totals['items'],
        ]);

        if ($lead !== null) {
            $quote->leads()->syncWithoutDetaching([$lead->getKey()]);
        }

        Event::dispatch('quote.create.after', $quote);

        return ['quote' => $this->present($quote->refresh(), true)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{catalog_total: float, discount_percent: float, discount_amount: float, tax_amount: float, sub_total: float, grand_total: float, items: array<int, array<string, mixed>>}
     */
    private function computeTotals(array $params): array
    {
        $quoteDiscPct = round((float) ($params['discount_percent'] ?? 0), 2);
        $catalogTotal = 0.0;
        $subTotal = 0.0;
        $discountAmount = 0.0;
        $taxAmount = 0.0;
        $items = [];

        foreach ($params['items'] ?? [] as $rawItem) {
            /** @var Product $product */
            $product = $this->productRepository->findOrFail((int) $rawItem['product_id']);
            $qty = (int) $rawItem['quantity'];
            $catalogPrice = (float) ($product->getAttribute('price') ?? 0);
            $price = isset($rawItem['price']) ? (float) $rawItem['price'] : $catalogPrice;
            $itemDiscPct = isset($rawItem['discount_percent']) ? (float) $rawItem['discount_percent'] : $quoteDiscPct;
            $taxPct = isset($rawItem['tax_percent']) ? (float) $rawItem['tax_percent'] : 0.0;

            $catalogTotal += round($catalogPrice * $qty, 4);
            $lineSub = round($price * $qty, 4);
            $lineDisc = round($lineSub * ($itemDiscPct / 100.0), 4);
            $lineTax = round(($lineSub - $lineDisc) * ($taxPct / 100.0), 4);
            $lineTotal = round($lineSub - $lineDisc + $lineTax, 4);

            $subTotal += $lineSub;
            $discountAmount += $lineDisc;
            $taxAmount += $lineTax;

            $items[] = [
                'product_id' => $product->getKey(),
                'sku' => (string) $product->getAttribute('sku'),
                'name' => (string) $product->getAttribute('name'),
                'quantity' => $qty,
                'price' => round($price, 4),
                'discount_percent' => round($itemDiscPct, 2),
                'discount_amount' => $lineDisc,
                'tax_percent' => round($taxPct, 2),
                'tax_amount' => $lineTax,
                'total' => $lineTotal,
            ];
        }

        return [
            'catalog_total' => round($catalogTotal, 2),
            'discount_percent' => $quoteDiscPct,
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'sub_total' => round($subTotal, 2),
            'grand_total' => round($subTotal - $discountAmount + $taxAmount, 2),
            'items' => $items,
        ];
    }
}
