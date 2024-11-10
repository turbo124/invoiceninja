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

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Http\Request;
use App\Utils\Traits\SavesDocuments;
use App\Jobs\Mailgun\ProcessMailgunWebhook;
use App\Http\Requests\Email\SendEmailRequest;
use App\Jobs\Mailgun\ProcessMailgunInboundWebhook;

/**
 * Class MailgunController.
 */
class MailgunController extends BaseController
{
    use SavesDocuments;

    public function __construct()
    {
    }

    /**
     * Process Mailgun Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/mailgun_webhook",
     *      operationId="mailgunWebhook",
     *      tags={"mailgun"},
     *      summary="Processing webhooks from Mailgun",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function webhook(Request $request)
    {

        $input = $request->all();
        
        nlog($input);

        if (\abs(\time() - $request['signature']['timestamp']) > 15) {
            return response()->json(['message' => 'Success'], 200);
        }

        if (\hash_equals(\hash_hmac('sha256', $input['signature']['timestamp'] . $input['signature']['token'], config('services.mailgun.webhook_signing_key')), $input['signature']['signature'])) {
            ProcessMailgunWebhook::dispatch($request->all())->delay(rand(2, 10));
        }

        return response()->json(['message' => 'Success.'], 200);
    }

    /**
     * Process Mailgun Inbound Webhook.
     *
     * IMPORTANT NOTICE: mailgun does NOT strip old sended emails, therefore all past attachements are present
     *
     * IMPORTANT NOTICE: mailgun saves the message and attachemnts for later retrieval, therefore we can process it within a async job for performance reasons
     *
     *
     * @OA\Post(
     *      path="/api/v1/mailgun_inbound_webhook",
     *      operationId="mailgunInboundWebhook",
     *      tags={"mailgun"},
     *      summary="Processing inbound webhooks from Mailgun",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function inboundWebhook(Request $request)
    {
        $input = $request->all();

        
        $authorizedByHash = \hash_equals(\hash_hmac('sha256', $input['timestamp'] . $input['token'], config('services.mailgun.webhook_signing_key')), $input['signature']);
        $authorizedByToken = $request->has('token') && $request->get('token') == config('ninja.inbound_mailbox.inbound_webhook_token');
        if (!$authorizedByHash && !$authorizedByToken) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        nlog($input);
        nlog("checks");
        /** Peppol invoice xml ingest */
        if(stripos($input['recipient'], "peppol_") !== false && stripos($input['from'], 'no-reply@mailer.storecove.com') !== false){
            
            $email_parts = explode("@", $input['recipient']);
            nlog($email_parts);

            $parts = explode("_", $email_parts[0]);

            nlog($parts);

            if(count($parts) != 4 && $parts[0] != 'peppol' && stripos('db-ninja-0', $parts[3]) !== false)
                return;

            $entity = ucfirst($parts[1]);
            $entity_id = $parts[2];
            $db = $parts[3];

            MultiDB::setDB($db);

            $class = "\\App\\Models\\".ucfirst(\Illuminate\Support\Str::camel($entity));

            /** @var \App\Models\Invoice $entity */
            $entity = $class::query()->withTrashed()->find($entity_id);
            
            if(!$entity){
                nlog("could not resolve entity for mailgun webhook");
                nlog($input);
                return;
            }

            foreach ($request->files as $file) {
                $this->saveDocuments($file, $entity, true);
            }


            if(empty($entity->sync))
                return; //just save the document, do not email it!

            $sync = $entity->sync;

            $request = new SendEmailRequest([
                'entity' => $sync->email->entity,
                'entity_id' => $entity->id,
                'template' => $sync->email->template,
                'subject' => $sync->email->subject,
                'body' => $sync->email->body,
                'cc_email' => $sync->email->cc_email,
            ]);

            $request->setUserResolver(fn () => $entity->user);  // Or auth()->user()

            app(\App\Http\Controllers\EmailController::class)->send($request);
            return;
        }

        if (!array_key_exists('sender', $input) || !array_key_exists('recipient', $input) || !array_key_exists('message-url', $input)) {
            nlog('Failed: Message could not be parsed, because required parameters are missing. Please ensure contacting this api-endpoint with a store & notify operation instead of a forward operation!');
            return response()->json(['message' => 'Failed. Missing Parameters. Use store and notify!'], 400);
        }

        // @turbo124 TODO: how to check for services.mailgun.webhook_signing_key on company level, when custom credentials are defined
        // TODO: validation for client mail credentials by recipient
        
        /** @var \App\Models\Company $company */
        $company = MultiDB::findAndSetDbByExpenseMailbox($input["recipient"]);

        if(!$company)
            return response()->json(['message' => 'Ok'], 200);  // Fail gracefully

        ProcessMailgunInboundWebhook::dispatch($input["sender"], $input["recipient"], $input["message-url"], $company)->delay(rand(2, 10));

        return response()->json(['message' => 'Success.'], 200);
    }
}
