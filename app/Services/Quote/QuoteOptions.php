<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quote;

use App\DataMapper\QuoteBackup;
use App\Models\Quote;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\QuoteFinancials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuoteOptions
{
    private const SCOPE_FIELDS = [
        'line_items', 'discount', 'is_amount_discount', 'uses_inclusive_taxes',
        'tax_name1', 'tax_rate1', 'tax_name2', 'tax_rate2', 'tax_name3', 'tax_rate3',
        'custom_surcharge1', 'custom_surcharge2', 'custom_surcharge3', 'custom_surcharge4',
        'custom_surcharge_tax1', 'custom_surcharge_tax2', 'custom_surcharge_tax3', 'custom_surcharge_tax4',
        'partial', 'partial_due_date', 'date', 'due_date', 'exchange_rate', 'terms', 'public_notes',
        'number', 'po_number', 'footer', 'design_id', 'client_id', 'company_id', 'location_id',
        'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4',
    ];

    public function financials(Quote $quote, array $line_items): array
    {
        return (new QuoteFinancials())->extract($this->documentHtml($quote, $line_items), $line_items);
    }

    public function displayItems(Quote $quote, array $selected_options = []): array
    {
        $optional_indexes = collect($quote->line_items ?? [])
            ->filter(fn ($item) => $item->is_optional ?? false)->keys()->all();
        $items = $this->preview($quote, $optional_indexes)->line_items;
        $selected_items = $this->preview($quote, $selected_options)->line_items;
        $selected_index = 0;

        foreach ($items as $index => $item) {
            $is_optional = in_array($index, $optional_indexes, true);
            if (! $is_optional || in_array($index, $selected_options)) {
                $item = $selected_items[$selected_index++];
            }
            $item->is_optional = $is_optional;
            $items[$index] = $item;
        }

        return $items;
    }

    public function documentHtml(Quote $quote, ?array $line_items = null, ?array $selected_options = null): string
    {
        $invitation = $quote->invitations()
            ->where('client_contact_id', auth()->guard('contact')->id())->first()
            ?? $quote->invitations()->firstOrFail();
        $invitation->setRelation('quote', $quote);

        return (new PdfService($invitation, options: [
            'quote_line_items' => $line_items ?? $quote->line_items,
            'selected_options' => $selected_options,
        ]))->boot()->getHtml();
    }

    public function preview(Quote $quote, array $selected_options): Quote
    {
        $selected_options = $this->validateSelection($quote, $selected_options);
        $preview = clone $quote;
        $preview->line_items = collect($quote->line_items ?? [])->filter(
            fn ($item, $index) => ! data_get($item, 'is_optional', false) || in_array($index, $selected_options, true)
        )->map(function ($item) {
            $item = clone $item;
            $item->is_optional = false;

            return $item;
        })->values()->all();

        $preview = $preview->calc()->getTempEntity();
        $preview->partial = min(max(0, (float) $preview->partial), max(0, (float) $preview->amount));

        return $preview;
    }

    public function validateSelection(Quote $quote, array $selected_options): array
    {
        $optional_indexes = collect($quote->line_items ?? [])
            ->filter(fn ($item) => data_get($item, 'is_optional', false))->keys()->all();

        Validator::make(['selected_options' => $selected_options], [
            'selected_options' => ['array'],
            'selected_options.*' => ['integer', 'distinct', Rule::in($optional_indexes)],
        ])->validate();

        return array_map('intval', $selected_options);
    }

    public function revision(Quote $quote): string
    {
        return hash('sha256', json_encode($quote->only(self::SCOPE_FIELDS), JSON_THROW_ON_ERROR));
    }

    public function assertConfirmed(Quote $quote): void
    {
        if ($quote->hasOptionSelection()) {
            $this->assertRevision($quote, $quote->backup->option_selection['revision']);
        }
    }

    public function assertEditable(Quote $quote, array $data): void
    {
        if (! $quote->hasOptionSelection()) {
            return;
        }

        $this->assertConfirmed($quote);
        $candidate = clone $quote;
        $candidate->fill($data);

        foreach ([...self::SCOPE_FIELDS, 'backup'] as $field) {
            if ($candidate->getAttribute($field) != $quote->getAttribute($field)) {
                throw ValidationException::withMessages([$field => ctrans('texts.quote_options_locked')]);
            }
        }
    }

    public function store(Quote $quote, array $selected_options, string $revision): string
    {
        $this->assertRevision($quote, $revision);
        $this->preview($quote, $selected_options);
        $options_key = Str::random(64);

        Cache::put('quote_options:' . $options_key, [
            'quote_id' => $quote->id,
            'company_key' => $quote->company->company_key,
            'client_contact_id' => auth()->guard('contact')->id(),
            'revision' => $revision,
            'selected_options' => $selected_options,
        ], now()->addHour());

        return $options_key;
    }

    public function restore(Quote $quote, string $options_key): Quote
    {
        return $this->preview($quote, $this->selection($quote, $options_key)['selected_options']);
    }

    private function selection(Quote $quote, string $options_key): array
    {
        $selection = Cache::get('quote_options:' . $options_key);

        abort_unless(
            is_array($selection)
            && $selection['quote_id'] === $quote->id
            && $selection['company_key'] === $quote->company->company_key
            && $selection['client_contact_id'] === auth()->guard('contact')->id(),
            404
        );

        $this->assertRevision($quote, $selection['revision']);

        return $selection;
    }

    public function apply(Quote $quote, string $options_key, ?string $user_input = null): Quote
    {
        $quote = DB::transaction(function () use ($quote, $options_key, $user_input) {
            $quote = Quote::query()->where('company_id', $quote->company_id)->lockForUpdate()->findOrFail($quote->id);

            abort_unless(
                $quote->client_id === auth()->guard('contact')->user()->client_id
                && in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
                && ! $quote->is_deleted && ! $quote->invoice_id && ! $quote->hasLapsedValidUntil(),
                409
            );
            abort_if($quote->hasOptionSelection() || $quote->sync?->dn_completed, 409);

            $selection = $this->selection($quote, $options_key);
            $preview = $this->preview($quote, $selection['selected_options']);
            $backup = $quote->backup ?? new QuoteBackup();
            $original_line_items = json_decode($quote->getRawOriginal('line_items'), true, 512, JSON_THROW_ON_ERROR);
            $original_partial = (float) $quote->partial;
            $original_po_number = $quote->po_number;
            $quote->line_items = $preview->line_items;
            $quote->partial = $preview->partial;

            if ($user_input !== null && strlen($user_input) > 2) {
                $quote->po_number = substr($user_input, 0, 180);
            }

            if (! $quote->design_id) {
                $quote->design_id = (int) $quote->decodePrimaryKey($quote->client->getSetting('quote_design_id'));
            }

            $quote = (new ApplyNumber($quote->client))->run($quote, for_approval: true);
            $quote = $quote->calc()->getInvoice()->refresh();
            $backup->option_selection = [
                'line_items' => $original_line_items,
                'partial' => $original_partial,
                'po_number' => $original_po_number,
                'selected_options' => array_map('intval', $selection['selected_options']),
                'contact_id' => auth()->guard('contact')->id(),
                'confirmed_at' => now()->toIso8601String(),
                'key' => $options_key,
                'revision' => $this->revision($quote),
            ];
            $quote->backup = $backup;
            $quote->saveQuietly();

            return $quote->refresh();
        });

        Cache::forget('quote_options:' . $options_key);

        return $quote;
    }

    public function restart(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $quote = Quote::query()->where('company_id', $quote->company_id)->lockForUpdate()->findOrFail($quote->id);
            $contact = auth()->guard('contact')->user();

            abort_unless(
                $quote->company_id === $contact->company_id && $quote->client_id === $contact->client_id
                && in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
                && ! $quote->is_deleted && ! $quote->invoice_id && ! $quote->hasLapsedValidUntil()
                && $quote->hasOptionSelection() && ! $quote->sync?->dn_completed,
                409
            );

            $this->assertConfirmed($quote);
            $backup = $quote->backup;
            $selection = $backup->option_selection;
            $quote->line_items = json_decode(json_encode($selection['line_items'], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            $quote->partial = $selection['partial'];
            $quote->po_number = $selection['po_number'];
            $backup->option_selection = null;
            $quote->backup = $backup;

            return $quote->calc()->getInvoice()->refresh();
        });
    }

    private function assertRevision(Quote $quote, string $revision): void
    {
        if (! hash_equals($this->revision($quote), $revision)) {
            throw ValidationException::withMessages(['selected_options' => ctrans('texts.quote_options_changed')]);
        }
    }
}
