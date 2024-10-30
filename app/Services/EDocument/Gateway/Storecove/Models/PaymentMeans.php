<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Gateway\Storecove\Models;

class PaymentMeans
{

    private array $code_keys = [
        '10' => 'cash',                      // In cash
        '20' => 'bank_cheque',               // Cheque
        '25' => 'cashiers_cheque',           // Certified cheque
        '30' => 'credit_transfer',           // Credit transfer
        '31' => 'debit_transfer',            // Debit transfer
        '48' => 'card',                      // Bank card (also used by sg_card)
        '49' => 'direct_debit',              // Direct debit
        '50' => 'se_plusgiro',               // Payment by postgiro
        '54' => 'card',                      // Credit card
        '55' => 'card',                      // Debit card
        '56' => 'se_bankgiro',               // Bankgiro
        '58' => 'sepa_credit_transfer',      // SEPA credit transfer
        '59' => 'sepa_direct_debit',         // SEPA direct debit
        '68' => 'online_payment_service',     // Online payment service
        '93' => 'sg_giro',                   // Reference giro
    ];


    private array $codes = [
        'cash' => ['description' => 'The invoice was/is paid in cash'],
        'bank_cheque' => ['description' => 'The invoice was/is paid in cash'],
        'cashiers_cheque' => ['description' => 'The invoice was/is paid in cash'],
        'credit_transfer' => [
            'description' => 'The amount is to be transfered into a bank account',
            'required' => ['account'], // Account Number
            'optional' => ['branche_code', 'holder'], //BIC - BSB, The account holder name
        ],
        'sepa_credit_transfer' => [
            'description' => 'The amount is to be transfered into a bank account',
            'required' => ['account'], // Account Number
            'optional' => ['branche_code', 'holder'], //BIC - BSB, The account holder name
        ],
        'debit_transfer' => [
            'description' => 'Used for CreditNotes. The amount is to be transfered by the sender of the document into the bank account of the receiver of the document. Relevant additional fields',
            'required' => ['account'], // Account Number
            'optional' => ['branche_code', 'holder'], //BIC - BSB, The account holder name
        ],
        'direct_debit' => [
            'description' => 'Direct debit. Relevant additional fields:',
            'required' => ['account','mandate'], // Account Number
            'optional' => ['holder','network'], //The account holder name - VISA,SEPA,MASTERCARD,
        ],
        'sepa_direct_debit' => [
            'description' => 'Direct debit. Relevant additional fields:',
            'required' => ['account'], // last 4 only of card
            'optional' => ['holder'], //The account holder name
        ],
        'card' => [
            'description' => 'E.g. credit or debit card. Relevant additional fields:',
            'required' => ['account',], // Account Number
            'optional' => ['holder','network'], //The account holder name, VISA,SEPA,MASTERCARD,
        ],
        'online_payment_service' => [
            'description' => 'An online payment service has been or will be used. Relevant additional fields:',
            'required' => ['network'], //ie PayPal
            'optional' => ['url'], //The URL to execute the paymetn
        ],
        'aunz_npp_payid' => [
            'description' => 'Australia/New Zealand New Payments Platform. Relevant additional fields:',
            'required' => ['account'], //PayID - email, abn, phone number
            'optional' => [],
        ],
        'aunz_npp' => [
            'description' => 'Australia/New Zealand New Payments Platform. Relevant additional fields:',
            'required' => ['account'], //PayID - email, abn, phone number
            'optional' => [],
        ],
        'aunz_npp_payto' => [
            'description' => 'Australia/New Zealand New Payments Platform. Relevant additional',
            'required' => ['account','mandate'], //PayID - email, abn, phone number
            'optional' => [],
        ],
        'se_bankgiro' =>  [
            'description' => 'Swedish Bankgiro. Relevant additional fields:',
            'required' => ['account'], 
            'optional' => ['holder'],
        ],
        'se_plusgiro' =>  [
            'description' => 'Swedish Plusgiro. Relevant additional fields:',
            'required' => ['account'],  //2-8 digits
            'optional' => ['holder'],
        ],
        'sg_giro' =>  [
            'description' => 'Singapore GIRO-system (direct debit). Relevant additional fields: none.',
            'required' => [], 
            'optional' => [],
        ], 
        'sg_card' =>  [
            'description' => 'Singapore CreditCard payment. Relevant additional fields: none.',
            'required' => [],  
            'optional' => [],
        ], 
        'sg_paynow' =>  [
            'description' => 'Singapore PayNow Corporate. Relevant additional fields:',
            'required' => ['account'], 
            'optional' => [],
        ],
        'it_mav' =>  [
            'description' => '',
            'required' => [],  
            'optional' => [],
        ],
        'it_pagopa' =>  [
            'description' => '',
            'required' => [], 
            'optional' => [],
        ],
        'undefined' =>  [
            'description' => '',
            'required' => [], 
            'optional' => [],
        ],

    ];

    public function __construct(
        public ?string $code = null, //payment means code
        public ?string $account = null, //account number
        public ?string $paymentId = null, //matching reference (invoice #)
        public ?string $branche_code = null, //bic
        public ?string $holder = null, //account holder name
        public ?string $network = null, // payment network, ie VISA, SEPA, MASTERCARD
        public ?string $mandate = null // mandate
        ){}


    public function setCodeProps($ubl_payment_means):self
    {

        $ubl_code = $ubl_payment_means->PaymentMeansCode;

        if (isset($this->code_keys[$ubl_code])) {
            $this->code = $this->code_keys[$ubl_code];
        }

        if (isset($this->codes[$ubl_code])) {
            $this->code = $ubl_code;
        }

        if($ubl_payment_means->CardAccount ?? false) {
            // If it is a card payment, the last 4 numbers may not be known?
            $this->account = '9999';
            $this->holder = strlen($ubl_payment_means->CardAccount->HolderName) > 1 ? $ubl_payment_means->CardAccount->HolderName : null;
            $this->network = $ubl_payment_means->CardAccount->CardTypeCode;  
        }

        if($ubl_payment_means->PayeeFinancialAccount ?? false){

            $this->account = $ubl_payment_means->PayeeFinancialAccount->ID->value;
            $this->branche_code = $ubl_payment_means->FinancialInstitutionBranch->ID->value ?? null;

        }

        return $this;
    }
}
