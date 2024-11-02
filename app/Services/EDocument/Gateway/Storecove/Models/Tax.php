<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Tax
{
	public ?string $country;
	public ?string $amount;
	public ?string $percentage;
	public ?string $category;
	public ?string $type;

	public function __construct(
		?string $country,
		?string $amount,
		?string $percentage,
		?string $category,
		?string $type
	) {
		$this->country = $country;
		$this->amount = $amount;
		$this->percentage = $percentage;
		$this->category = $category;
		$this->type = $type;
	}

	public function getCountry(): ?string
	{
		return $this->country;
	}

	public function getAmount(): ?string
	{
		return $this->amount;
	}

	public function getPercentage(): ?string
	{
		return $this->percentage;
	}

	public function getCategory(): ?string
	{
		return $this->category;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setCountry(?string $country): self
	{
		$this->country = $country;
		return $this;
	}

	public function setAmount(?string $amount): self
	{
		$this->amount = $amount;
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

	public function setType(?string $type): self
	{
		$this->type = $type;
		return $this;
	}
}
