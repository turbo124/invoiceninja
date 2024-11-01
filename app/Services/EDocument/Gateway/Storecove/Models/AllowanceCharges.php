<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class AllowanceCharges
{
    #[SerializedPath('[cbc:Amount][#]')]
    public ?string $amount_excluding_vat;

    // #[SerializedPath('[cbc:BaseAmount][#]')]
    public ?string $amount_excluding_tax;

    #[SerializedPath('[cbc:BaseAmount][#]')]
    public ?string $base_amount_excluding_tax;

    #[SerializedPath('[cbc:Amount][@currencyID]')]
    public ?string $amount_including_tax;

    #[SerializedPath('[cbc:BaseAmount][@currencyID]')]
    public ?string $base_amount_including_tax;

    // #[SerializedPath('[cac:TaxCategory]')]
    // public ?Tax $tax;

    #[SerializedPath('[cac:TaxCategory]')]
    /** @var TaxesDutiesFees[] */
    public ?array $taxes_duties_fees;

    #[SerializedPath('[cbc:AllowanceChargeReason]')]
    public ?string $reason;

    #[SerializedPath('[cbc:AllowanceChargeReasonCode]')]
    public ?string $reason_code;

	/**
	 * @param TaxesDutiesFees[] $taxes_duties_fees
	 */
	public function __construct(
		?string $amount_excluding_vat,
		?string $amount_excluding_tax,
		?string $base_amount_excluding_tax,
		?string $amount_including_tax,
		?string $base_amount_including_tax,
		?Tax $tax,
		?array $taxes_duties_fees,
		?string $reason,
		?string $reason_code
	) {
		$this->amount_excluding_vat = $amount_excluding_vat;
		$this->amount_excluding_tax = $amount_excluding_tax;
		$this->base_amount_excluding_tax = $base_amount_excluding_tax;
		$this->amount_including_tax = $amount_including_tax;
		$this->base_amount_including_tax = $base_amount_including_tax;
		// $this->tax = $tax;
		$this->taxes_duties_fees = $taxes_duties_fees;
		$this->reason = $reason;
		$this->reason_code = $reason_code;
	}

	public function getAmountExcludingVat(): ?string
	{
		return $this->amount_excluding_vat;
	}

	public function getAmountExcludingTax(): ?string
	{
		return $this->amount_excluding_tax;
	}

	public function getBaseAmountExcludingTax(): ?string
	{
		return $this->base_amount_excluding_tax;
	}

	public function getAmountIncludingTax(): ?string
	{
		return $this->amount_including_tax;
	}

	public function getBaseAmountIncludingTax(): ?string
	{
		return $this->base_amount_including_tax;
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

	public function getReason(): ?string
	{
		return $this->reason;
	}

	public function getReasonCode(): ?string
	{
		return $this->reason_code;
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

	public function setBaseAmountExcludingTax(?string $base_amount_excluding_tax): self
	{
		$this->base_amount_excluding_tax = $base_amount_excluding_tax;
		return $this;
	}

	public function setAmountIncludingTax(?string $amount_including_tax): self
	{
		$this->amount_including_tax = $amount_including_tax;
		return $this;
	}

	public function setBaseAmountIncludingTax(?string $base_amount_including_tax): self
	{
		$this->base_amount_including_tax = $base_amount_including_tax;
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

	public function setReason(?string $reason): self
	{
		$this->reason = $reason;
		return $this;
	}

	public function setReasonCode(?string $reason_code): self
	{
		$this->reason_code = $reason_code;
		return $this;
	}
}
