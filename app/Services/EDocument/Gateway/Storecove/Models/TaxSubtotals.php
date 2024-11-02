<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class TaxSubtotals
{

    #[SerializedPath('[cbc:TaxAmount][#]')]
    public ?string $tax_amount;

    public ?string $country;

    #[SerializedPath('[cbc:TaxableAmount][#]')]
    public ?string $taxable_amount;

    #[SerializedPath('[cac:TaxCategory][cbc:Percent]')]
    public ?string $percentage;

    #[SerializedPath('[cac:TaxCategory][cbc:ID][#]')]
    public ?string $category;

	public function __construct(
		?string $tax_amount,
		?string $country,
		?string $taxable_amount,
		?string $percentage,
		?string $category
	) {
		$this->tax_amount = $tax_amount;
		$this->country = $country;
		$this->taxable_amount = $taxable_amount;
		$this->percentage = $percentage;
		$this->category = $category;
	}

	public function getTaxAmount(): ?string
	{
		return $this->tax_amount;
	}

	public function getCountry(): ?string
	{
		return $this->country;
	}

	public function getTaxableAmount(): ?string
	{
		return $this->taxable_amount;
	}

	public function getPercentage(): ?string
	{
		return $this->percentage;
	}

	public function getCategory(): ?string
	{
		return $this->category;
	}

	public function setTaxAmount(?string $tax_amount): self
	{
		$this->tax_amount = $tax_amount;
		return $this;
	}

	public function setCountry(?string $country): self
	{
		$this->country = $country;
		return $this;
	}

	public function setTaxableAmount(?string $taxable_amount): self
	{
		$this->taxable_amount = $taxable_amount;
		return $this;
	}

	public function setPercentage(?string $percentage): self
	{
		$this->percentage = $percentage;
		return $this;
	}

	public function setCategory(?string $category): self
	{
		$this->category = $category;
		return $this;
	}
}
