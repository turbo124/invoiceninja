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

namespace App\Services\EDocument\Jobs;

use App\Utils\Ninja;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\EDocument\Gateway\Storecove\Storecove;

class SendEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    
    public $deleteWhenMissingModels = true;

    public function __construct(private string $entity, private int $id, private string $db)
    {
    }

    public function backoff()
    {
        return [rand(5, 29), rand(30, 59), rand(240, 360), 3600, 7200];
    }

    public function handle(Storecove $storecove)
    {
        MultiDB::setDB($this->db);
    
        nlog("trying");

        $model = $this->entity::find($this->id);

        /** Concrete implementation current linked to Storecove only */
        $p = new Peppol($model);
        $p->run();
        $identifiers = $p->gateway->mutator->setClientRoutingCode()->getStorecoveMeta();

        $result = $storecove->build($model)->getResult();

        if (count($result['errors']) > 0) {
            nlog($result);
            return $result['errors'];
        }
        
        $payload = [
            'legal_entity_id' => $model->company->legal_entity_id,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            'document' => [
                'document_type' => 'invoice',
                'invoice' => $result['document'],
            ],
            'tenant_id' => $model->company->company_key,
            'routing' => $identifiers['routing'],
            
            //
            'account_key' => $model->company->account->key,
            'e_invoicing_token' => $model->company->account->e_invoicing_token,
            'identifiers' => $identifiers,
        ];
        
        /** Concrete implementation current linked to Storecove only */

        //@testing only
        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $r = $sc->sendJsonDocument($payload);

        if (is_string($r)) {
            return $this->writeActivity($model, $r);
        }
        else {
                // nlog($r->body());
        }
        
        return;


        if(Ninja::isSelfHost() && ($model instanceof Invoice) && $model->company->legal_entity_id)
        {
            
            $r = Http::withHeaders($this->getHeaders())
                ->post(config('ninja.hosted_ninja_url')."/api/einvoice/submission", $payload);

            if($r->successful()) {
                nlog("Model {$model->number} was successfully sent for third party processing via hosted Invoice Ninja");
            
                $data = $r->json();
                return $this->writeActivity($model, $data['guid']);

            }

            if($r->failed()) {
                nlog("Model {$model->number} failed to be accepted by invoice ninja, error follows:");
                nlog($r->getBody()->getContents());
            }

            //self hosted sender
        }

        if(Ninja::isHosted() && ($model instanceof Invoice) && $model->company->legal_entity_id)
        {

            $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
            $r = $sc->sendJsonDocument($payload);

            if(is_string($r))
                return $this->writeActivity($model, $r);
                
            if($r->failed()) {
                nlog("Model {$model->number} failed to be accepted by invoice ninja, error follows:");
                nlog($r->getBody()->getContents());
            }

        }

    }

    private function writeActivity($model, string $guid)
    {
        $activity = new Activity();
        $activity->user_id = $model->user_id;
        $activity->client_id = $model->client_id ?? $model->vendor_id;
        $activity->company_id = $model->company_id;
        $activity->activity_type_id = Activity::EMAIL_EINVOICE_SUCCESS;
        $activity->invoice_id = $model->id;
        $activity->notes = str_replace('"', '', $guid);

        $activity->save();

        $std = new \stdClass;
        $std->guid = str_replace('"', '', $guid);
        $model->backup = $std;
        $model->saveQuietly();

    }
    
    /**
     * Self hosted request headers
     *
     * @return array
     */
    private function getHeaders(): array
    {
        return [
            'X-API-SELF-HOST-TOKEN' => config('ninja.license_key'),
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type" => "application/json",
        ];
    }

    public function failed($exception = null)
    {
        if ($exception) {
            nlog("EXCEPTION:: SENDEDOCUMENT::");
            nlog($exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->entity.$this->id.$this->db)];
    }
}
