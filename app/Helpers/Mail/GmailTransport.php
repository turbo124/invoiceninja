<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Helpers\Mail;

use App\Libraries\MultiDB;
use App\Models\User;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;

/**
 * GmailTransport.
 */
class GmailTransport extends Transport
{
    /**
     * The Gmail instance.
     *
     * @var \Dacastro4\LaravelGmail\Services\Message\Mail
     */
    protected $gmail;

    /**
     * The GMail OAuth Token.
     * @var string token
     */
    protected $token;

    /**
     * Create a new Gmail transport instance.
     *

     * @return void
     */
    public function __construct(Mail $gmail, string $token)
    {
        $this->gmail = $gmail;
        $this->token = $token;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        /*We should nest the token in the message and then discard it as needed*/

        $this->beforeSendPerformed($message);

        $this->gmail->using($this->token);
        $this->gmail->to($message->getTo());
        $this->gmail->from($message->getFrom());
        $this->gmail->subject($message->getSubject());
        $this->gmail->message($message->getBody());
        //$this->gmail->message($message->toString());
        $this->gmail->cc($message->getCc());
        $this->gmail->bcc($message->getBcc());

        \Log::error(print_r($message->getChildren(), 1));

        foreach ($message->getChildren() as $child) {
            $this->gmail->attach($child);
        } //todo this should 'just work'

        $this->gmail->send();

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}
