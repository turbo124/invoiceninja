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

namespace Tests\Feature\ClientPortal;

use App\DataMapper\QuoteSync;
use App\Livewire\Flow2\DocuNinja;
use App\Livewire\Flow2\DocuNinjaLoader;
use App\Livewire\Sign;
use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

class QuoteApprovalLivewireTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');

        $settings = $this->client->settings;
        $settings->auto_convert_quote = false;
        $this->client->settings = $settings;
        $this->client->save();

        $this->quote->sync = new QuoteSync();
        $this->quote->saveQuietly();
    }

    public function testDocuNinjaSignatureCompletesThePortalQuoteApprovalFlow(): void
    {
        $invitation = $this->quote->invitations()
            ->where('client_contact_id', $this->contact->id)
            ->firstOrFail();
        $request_hash = Str::random(64);

        Cache::put($request_hash, [
            'client_contact_id' => $this->contact->id,
            'request' => [
                'action' => 'approve',
                'process' => 'true',
                'quotes' => [$this->quote->hashed_id],
            ],
        ], now()->addHour());

        Livewire::test(Sign::class, [
            '_key' => $invitation->key,
            'invitation_id' => $invitation->id,
            'entity_type' => 'quote',
            'db' => $this->company->db,
            'request_hash' => $request_hash,
        ])
            ->assertSet('initializing', false)
            ->assertSet('component', DocuNinjaLoader::class)
            ->call('docuninjaLoaderReady')
            ->assertSet('docu_ninja_ready', true)
            ->assertSet('component', DocuNinja::class)
            ->call('docuNinjaSignatureCaptured')
            ->assertSet('signature_accepted', true)
            ->assertRedirectToRoute('client.quotes.approval.continue', [
                'request_hash' => $request_hash,
            ]);

        $this->assertTrue((bool) $this->quote->fresh()->sync?->dn_completed);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
        $this->assertTrue(Cache::has($request_hash));

        $this->get(route('client.quotes.approval.continue', $request_hash))
            ->assertOk()
            ->assertSee('method="post"', false);

        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'request_hash' => $request_hash,
        ])->assertRedirect();

        $this->assertSame(Quote::STATUS_APPROVED, $this->quote->fresh()->status_id);
        $this->assertFalse(Cache::has($request_hash));

        Cache::forget($invitation->key);
    }

    public function testStandaloneQuoteSignatureSignalsTheEmbeddingPortalFlow(): void
    {
        $invitation = $this->quote->invitations()
            ->where('client_contact_id', $this->contact->id)
            ->firstOrFail();

        Livewire::test(Sign::class, [
            '_key' => $invitation->key,
            'invitation_id' => $invitation->id,
            'entity_type' => 'quote',
            'db' => $this->company->db,
            'request_hash' => null,
        ])
            ->call('docuNinjaSignatureCaptured')
            ->assertSet('signature_accepted', true)
            ->assertDispatched('quote-signed')
            ->assertNoRedirect();

        $this->assertTrue((bool) $this->quote->fresh()->sync?->dn_completed);

        Cache::forget($invitation->key);
    }
}
