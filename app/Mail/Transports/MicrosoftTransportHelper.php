<?php


namespace App\Mail\Transports;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;

class MicrosoftTransportHelper
{

    public function getMailBody($email, SentMessage $raw_message): array
    {
        $attachments = $this->attachAttachments($email);
        $recipients = $this->setRecipients($raw_message);
        return $mailBody = array("Message" => array(
            "subject" => $email->getSubject(),
            "body" => array(
                "contentType" => "html",
                "content" => $email->getHtmlBody()
            ),

            "toRecipients" => $recipients,
            "attachments" => $attachments
        )
        );
    }

    private function setRecipients(SentMessage $email): array
    {
        $recipients = [];
        foreach ($email->getEnvelope()->getRecipients() as $recepient) {
            $recipients[] = [
                "emailAddress" => [
                    "name" => $recepient->getEncodedName(),
                    "address" => $recepient->getEncodedAddress(),
                ]
            ];
        }
        return $recipients;
    }

    private function attachAttachments($email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                "@odata.type" => "#microsoft.graph.fileAttachment",
                "name" => $attachment->getFilename(),
                "contentType" => $attachment->getContentType(),
                "contentBytes" => base64_encode($attachment->getBody())];
        }
        return $attachments;
    }
}
