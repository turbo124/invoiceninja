<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class PaymentTerms
{
	public ?string $note;

	public function __construct(?string $note)
	{
		$this->note = $note;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function setNote(?string $note): self
	{
		$this->note = $note;
		return $this;
	}
}
