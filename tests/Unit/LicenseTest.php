<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\DataMapper\EInvoice\TaxEntity;
use Tests\TestCase;
use App\Models\User;
use App\Models\Design;
use App\Models\License;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 */
class LicenseTest extends TestCase
{
   use DatabaseTransactions;
   
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function stubLicense($tes = [])
    {
        $entities = [];

        foreach($tes as $te)
        {
            $te = new TaxEntity([
                'legal_entity_id' => $te['legal_entity_id'] ?? 1,
                'company_key' => $te['company_key'] ?? '',
                'received_documents' => $te['received_documents'] ?? []
            ]);

            $entities[] = $te;
        }

        $l = new License();
        $l->license_key = Str::random(32);
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->entities = $entities;
        $l->save();

        return $l;

    }

    public function testTaxEntiyFind()
    {
        $tes = [
            [
                'legal_entity_id' => rand(1,100),
                'company_key' => \Illuminate\Support\Str::random(32),
                'received_documents' => []
            ],
            [
                'legal_entity_id' => rand(1,100),
                'company_key' => \Illuminate\Support\Str::random(32),
                'received_documents' => []
            ],
            [
                'legal_entity_id' => 50,
                'company_key' => 'abcd',
                'received_documents' => []
            ]
        ];

        $l = $this->stubLicense($tes);

        $this->assertCount(3, $l->entities);

        $search = $l->findEntity('legal_entity_id', 50);

        $this->assertEquals(50, $search->legal_entity_id);
        $this->assertEquals('abcd', $search->company_key);

        $search = $l->findEntity('company_key', 'abcd');

        $this->assertEquals(50, $search->legal_entity_id);
        $this->assertEquals('abcd', $search->company_key);

    }

    public function testTaxEntityAddRemove()
    {
        $l = $this->stubLicense();

        $this->assertCount(0, $l->entities);

        $te = new TaxEntity([
            'legal_entity_id' => 123,
            'company_key' => 'qqqq',
            'received_documents' => []
        ]);

        $l->addEntity($te);
        $l->refresh();

        $this->assertCount(1, $l->entities);

        $l->removeEntity($te);
        $l->refresh();

        $this->assertCount(0, $l->entities);


    }


    public function testTaxEntityAddUpdate()
    {
        $l = $this->stubLicense();

        $this->assertCount(0, $l->entities);

        $te = new TaxEntity([
            'legal_entity_id' => 123,
            'company_key' => 'qqqq',
            'received_documents' => []
        ]);

        $l->addEntity($te);
        $l->refresh();

        $this->assertCount(1, $l->entities);

        $entity = $l->findEntity('legal_entity_id', 123);

        $this->assertNotNull($entity);

        $entity->legal_entity_id = 555;

        $l->updateEntity($entity,'company_key');
        $l->refresh();

        $entity = $l->findEntity('company_key', 'qqqq');

        $this->assertNotNull($entity);

        $this->assertEquals('qqqq', $entity->company_key);
        $this->assertEquals(555, $entity->legal_entity_id);
        
    }



    public function testLicenseValidity()
    {
        $l = new License();

        $l->license_key = Str::random(32);  
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->save();

        $this->assertInstanceOf(License::class, $l);

        $this->assertTrue($l->isValid());
    }


    public function testLicenseValidityExpired()
    {
        $l = new License();

        $l->license_key = Str::random(32);
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->save();

        $l->created_at = now()->subYears(2);
        $l->save();

        $this->assertInstanceOf(License::class, $l);

        $this->assertFalse($l->isValid());
    }

   

}
