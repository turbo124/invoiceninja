<?php

namespace App\Ninja\PaymentDrivers;

class PagSeguroPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'transactionReference';
}
