<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Notifications\Ninja;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class DomainRenewedNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }

    public function toSlack($notifiable)
    {
        $content = "Domain Certificate Renewed:\n";
        $content .= "{$this->domain}\n";

        return (new SlackMessage())
            ->success()
            ->from(ctrans('texts.notification_bot'))
            ->image('https://app.invoiceninja.com/favicon.png')
            ->content($content);
    }
}
