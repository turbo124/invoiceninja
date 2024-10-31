<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class InvoiceLines
{
	public ?string $line_id;
	public ?string $description;
	public ?string $name;
	public ?string $order_line_reference_line_id;
	public ?string $invoice_period;
	public ?float $item_price;
	public ?float $quantity;
	public ?float $base_quantity;
	public ?string $quantity_unit_code;
	public ?string $allowance_charge;
	/** @var AllowanceCharges[] */
	public ?array $allowance_charges;
	public ?string $amount_excluding_vat;
	public ?string $amount_excluding_tax;
	public ?string $amount_including_tax;
	public ?Tax $tax;
	/** @var TaxesDutiesFees[] */
	public ?array $taxes_duties_fees;
	public ?string $accounting_cost;
	/** @var References[] */
	public ?array $references;
	/** @var AdditionalItemProperties[] */
	public ?array $additional_item_properties;
	public ?string $sellers_item_identification;
	public ?string $buyers_item_identification;
	public ?string $standard_item_identification;
	public ?string $standard_item_identification_scheme_id;
	public ?string $standard_item_identification_scheme_agency_id;
	public ?string $note;

	/**
	 * @param AllowanceCharges[] $allowance_charges
	 * @param TaxesDutiesFees[] $taxes_duties_fees
	 * @param References[] $references
	 * @param AdditionalItemProperties[] $additional_item_properties
	 */
	public function __construct(
		?string $line_id,
		?string $description,
		?string $name,
		?string $order_line_reference_line_id,
		?string $invoice_period,
		?float $item_price,
		?float $quantity,
		?float $base_quantity,
		?string $quantity_unit_code,
		?string $allowance_charge,
		?array $allowance_charges,
		?string $amount_excluding_vat,
		?string $amount_excluding_tax,
		?string $amount_including_tax,
		?Tax $tax,
		?array $taxes_duties_fees,
		?string $accounting_cost,
		?array $references,
		?array $additional_item_properties,
		?string $sellers_item_identification,
		?string $buyers_item_identification,
		?string $standard_item_identification,
		?string $standard_item_identification_scheme_id,
		?string $standard_item_identification_scheme_agency_id,
		?string $note
	) {
		$this->line_id = $line_id;
		$this->description = $description;
		$this->name = $name;
		$this->order_line_reference_line_id = $order_line_reference_line_id;
		$this->invoice_period = $invoice_period;
		$this->item_price = $item_price;
		$this->quantity = $quantity;
		$this->base_quantity = $base_quantity;
		$this->quantity_unit_code = $quantity_unit_code;
		$this->allowance_charge = $allowance_charge;
		$this->allowance_charges = $allowance_charges;
		$this->amount_excluding_vat = $amount_excluding_vat;
		$this->amount_excluding_tax = $amount_excluding_tax;
		$this->amount_including_tax = $amount_including_tax;
		$this->tax = $tax;
		$this->taxes_duties_fees = $taxes_duties_fees;
		$this->accounting_cost = $accounting_cost;
		$this->references = $references;
		$this->additional_item_properties = $additional_item_properties;
		$this->sellers_item_identification = $sellers_item_identification;
		$this->buyers_item_identification = $buyers_item_identification;
		$this->standard_item_identification = $standard_item_identification;
		$this->standard_item_identification_scheme_id = $standard_item_identification_scheme_id;
		$this->standard_item_identification_scheme_agency_id = $standard_item_identification_scheme_agency_id;
		$this->note = $note;
	}

	public function getLineId(): ?string
	{
		return $this->line_id;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function getOrderLineReferenceLineId(): ?string
	{
		return $this->order_line_reference_line_id;
	}

	public function getInvoicePeriod(): ?string
	{
		return $this->invoice_period;
	}

	public function getItemPrice(): ?float
	{
		return $this->item_price;
	}

	public function getQuantity(): ?float
	{
		return $this->quantity;
	}

	public function getBaseQuantity(): ?float
	{
		return $this->base_quantity;
	}

	public function getQuantityUnitCode(): ?string
	{
		return $this->quantity_unit_code;
	}

	public function getAllowanceCharge(): ?string
	{
		return $this->allowance_charge;
	}

	/**
	 * @return AllowanceCharges[]
	 */
	public function getAllowanceCharges(): ?array
	{
		return $this->allowance_charges;
	}

	public function getAmountExcludingVat(): ?string
	{
		return $this->amount_excluding_vat;
	}

	public function getAmountExcludingTax(): ?string
	{
		return $this->amount_excluding_tax;
	}

	public function getAmountIncludingTax(): ?string
	{
		return $this->amount_including_tax;
	}

	public function getTax(): ?Tax
	{
		return $this->tax;
	}

	/**
	 * @return TaxesDutiesFees[]
	 */
	public function getTaxesDutiesFees(): ?array
	{
		return $this->taxes_duties_fees;
	}

	public function getAccountingCost(): ?string
	{
		return $this->accounting_cost;
	}

	/**
	 * @return References[]
	 */
	public function getReferences(): ?array
	{
		return $this->references;
	}

	/**
	 * @return AdditionalItemProperties[]
	 */
	public function getAdditionalItemProperties(): ?array
	{
		return $this->additional_item_properties;
	}

	public function getSellersItemIdentification(): ?string
	{
		return $this->sellers_item_identification;
	}

	public function getBuyersItemIdentification(): ?string
	{
		return $this->buyers_item_identification;
	}

	public function getStandardItemIdentification(): ?string
	{
		return $this->standard_item_identification;
	}

	public function getStandardItemIdentificationSchemeId(): ?string
	{
		return $this->standard_item_identification_scheme_id;
	}

	public function getStandardItemIdentificationSchemeAgencyId(): ?string
	{
		return $this->standard_item_identification_scheme_agency_id;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function setLineId(?string $line_id): self
	{
		$this->line_id = $line_id;
		return $this;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	public function setName(?string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function setOrderLineReferenceLineId(?string $order_line_reference_line_id): self
	{
		$this->order_line_reference_line_id = $order_line_reference_line_id;
		return $this;
	}

	public function setInvoicePeriod(?string $invoice_period): self
	{
		$this->invoice_period = $invoice_period;
		return $this;
	}

	public function setItemPrice(?float $item_price): self
	{
		$this->item_price = $item_price;
		return $this;
	}

	public function setQuantity(?float $quantity): self
	{
		$this->quantity = $quantity;
		return $this;
	}

	public function setBaseQuantity(?float $base_quantity): self
	{
		$this->base_quantity = $base_quantity;
		return $this;
	}

	public function setQuantityUnitCode(?string $quantity_unit_code): self
	{
		$this->quantity_unit_code = $quantity_unit_code;
		return $this;
	}

	public function setAllowanceCharge(?string $allowance_charge): self
	{
		$this->allowance_charge = $allowance_charge;
		return $this;
	}

	/**
	 * @param AllowanceCharges[] $allowance_charges
	 */
	public function setAllowanceCharges(?array $allowance_charges): self
	{
		$this->allowance_charges = $allowance_charges;
		return $this;
	}

	public function setAmountExcludingVat(?string $amount_excluding_vat): self
	{
		$this->amount_excluding_vat = $amount_excluding_vat;
		return $this;
	}

	public function setAmountExcludingTax(?string $amount_excluding_tax): self
	{
		$this->amount_excluding_tax = $amount_excluding_tax;
		return $this;
	}

	public function setAmountIncludingTax(?string $amount_including_tax): self
	{
		$this->amount_including_tax = $amount_including_tax;
		return $this;
	}

	public function setTax(?Tax $tax): self
	{
		$this->tax = $tax;
		return $this;
	}

	/**
	 * @param TaxesDutiesFees[] $taxes_duties_fees
	 */
	public function setTaxesDutiesFees(?array $taxes_duties_fees): self
	{
		$this->taxes_duties_fees = $taxes_duties_fees;
		return $this;
	}

	public function setAccountingCost(?string $accounting_cost): self
	{
		$this->accounting_cost = $accounting_cost;
		return $this;
	}

	/**
	 * @param References[] $references
	 */
	public function setReferences(?array $references): self
	{
		$this->references = $references;
		return $this;
	}

	/**
	 * @param AdditionalItemProperties[] $additional_item_properties
	 */
	public function setAdditionalItemProperties(?array $additional_item_properties): self
	{
		$this->additional_item_properties = $additional_item_properties;
		return $this;
	}

	public function setSellersItemIdentification(?string $sellers_item_identification): self
	{
		$this->sellers_item_identification = $sellers_item_identification;
		return $this;
	}

	public function setBuyersItemIdentification(?string $buyers_item_identification): self
	{
		$this->buyers_item_identification = $buyers_item_identification;
		return $this;
	}

	public function setStandardItemIdentification(?string $standard_item_identification): self
	{
		$this->standard_item_identification = $standard_item_identification;
		return $this;
	}

	public function setStandardItemIdentificationSchemeId(?string $standard_item_identification_scheme_id): self
	{
		$this->standard_item_identification_scheme_id = $standard_item_identification_scheme_id;
		return $this;
	}

	public function setStandardItemIdentificationSchemeAgencyId(?string $standard_item_identification_scheme_agency_id): self
	{
		$this->standard_item_identification_scheme_agency_id = $standard_item_identification_scheme_agency_id;
		return $this;
	}

	public function setNote(?string $note): self
	{
		$this->note = $note;
		return $this;
	}
}
