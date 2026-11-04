<?php

namespace Tests\Unit\Transformers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Transformers\ArraySerializer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceProfileTransformerTest extends TestCase
{
    public static function referenceProfiles(): array
    {
        return [
            'project' => [Project::class, ['private_notes' => '', 'budgeted_hours' => 0.0, 'budgeted_amount' => 0.0]],
            'client' => [Client::class, ['private_notes' => '', 'balance' => 0.0, 'paid_to_date' => 0.0, 'credit_balance' => 0.0, 'payment_balance' => 0.0, 'client_hash' => '']],
            'vendor' => [Vendor::class, ['private_notes' => '']],
            'invoice' => [Invoice::class, ['private_notes' => '', 'amount' => 0.0, 'balance' => 0.0, 'paid_to_date' => 0.0]],
        ];
    }

    #[DataProvider('referenceProfiles')]
    public function testProfileRetainsLabelsAndNeutralizesSensitiveFields(string $model_class, array $redacted_fields): void
    {
        $record = $this->referenceRecord($model_class);
        $transformer_class = 'App\\Transformers\\'.class_basename($record).'ProfileTransformer';
        $transformer = new $transformer_class();
        $full_transformer_class = 'App\\Transformers\\'.class_basename($record).'Transformer';
        $expected = (new $full_transformer_class())->transform($record);
        $this->assertInstanceOf($full_transformer_class, $transformer);
        $this->assertSame($record->hashed_id, $expected['id']);
        $label = $record instanceof Invoice ? 'number' : 'name';
        $this->assertSame($record->{$label}, $expected[$label]);
        if ($record instanceof Client || $record instanceof Vendor) {
            $this->assertSame($record->name, $expected['display_name']);
        }
        $expected = array_merge($expected, $redacted_fields);

        DB::connection()->beforeExecuting(static function () {
            throw new LogicException('A profile must not query child records to serialize its fields.');
        });
        $data = $transformer->transform($record);
        $this->assertEquals($expected, $data);
        foreach ($redacted_fields as $field => $neutral_value) {
            $this->assertSame($neutral_value, $data[$field], $field);
        }
        $this->assertSame([], $transformer->getDefaultIncludes());
        $this->assertSame([], $transformer->getAvailableIncludes());

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $manager->parseIncludes('documents,contacts,gateway_tokens,client,project.tasks,payments,paymentables,invitations');
        $this->assertEquals($expected, $manager->createData(new Item($record, $transformer))->toArray());
        $this->assertSame('Private fixture notes', $record->private_notes);
    }

    #[DataProvider('referenceProfiles')]
    public function testFullTransformerRetainsSensitiveFieldsAndIncludes(string $model_class, array $redacted_fields): void
    {
        $record = $this->referenceRecord($model_class);
        $transformer_class = 'App\\Transformers\\'.class_basename($record).'Transformer';
        $transformer = new $transformer_class();

        $data = $transformer->transform($record);
        $this->assertSame($record->hashed_id, $data['id']);
        $this->assertSame('Public fixture notes', $data['public_notes']);
        foreach ($redacted_fields as $field => $neutral_value) {
            $this->assertEquals($record->{$field}, $data[$field], $field);
            $this->assertNotEquals($neutral_value, $data[$field], $field);
        }
        $this->assertContains('documents', $transformer->getDefaultIncludes());
        $this->assertNotEmpty($transformer->getAvailableIncludes());
    }

    public static function unnamedProfiles(): array
    {
        return ['client' => [Client::class], 'vendor' => [Vendor::class]];
    }

    #[DataProvider('unnamedProfiles')]
    public function testProfileRetainsContactDisplayLabelWithoutExposingContacts(string $model_class): void
    {
        $record = $this->referenceRecord($model_class);
        $record->name = '';
        $contact_class = $record instanceof Client ? ClientContact::class : VendorContact::class;
        $contact = (new $contact_class())->forceFill([
            'first_name' => 'Display',
            'last_name' => 'Contact',
            'email' => 'private@example.test',
        ]);
        $record->setRelation($record instanceof Client ? 'contacts' : 'primary_contact', new Collection([$contact]));
        $transformer_class = 'App\\Transformers\\'.class_basename($record).'ProfileTransformer';
        $data = (new $transformer_class())->transform($record);

        $this->assertSame('Display Contact', $data['display_name']);
        $this->assertSame('', $data['name']);
        $this->assertArrayNotHasKey('contacts', $data);
        $this->assertStringNotContainsString($contact->email, json_encode($data));
    }

    private function referenceRecord(string $model_class): Model
    {
        $record = (new $model_class())->forceFill([
            'id' => 101,
            'user_id' => 7,
            'assigned_user_id' => 8,
            'client_id' => 9,
            'name' => 'Reference fixture',
            'number' => 'INV-REFERENCE',
            'private_notes' => 'Private fixture notes',
            'public_notes' => 'Public fixture notes',
            'amount' => 175,
            'balance' => 125,
            'paid_to_date' => 50,
            'credit_balance' => 25,
            'payment_balance' => 10,
            'client_hash' => 'private-client-hash',
            'budgeted_hours' => 80,
            'budgeted_amount' => 9876,
            'task_rate' => 125,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 00:00:00',
        ]);
        $record->setRelation('tags', new Collection());

        return $record;
    }
}
