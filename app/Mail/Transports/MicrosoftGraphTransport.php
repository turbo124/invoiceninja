<?php


namespace App\Mail\Transports;


use Illuminate\Mail\Mailer;
use Laravel\Socialite\Facades\Socialite;
use Microsoft\Graph\Graph;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class MicrosoftGraphTransport extends AbstractTransport
{

    protected function doSend(SentMessage $message): void
    {

        $graph = new Graph();
        $helper = new MicrosoftTransportHelper();
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        //todo see how we will fetch new token
        $graph->setAccessToken("EwBwA8l6BAAUkj1NuJYtTVha+Mogk+HEiPbQo04AAWwUxTcL64rLQQxKrb9zYw+lfSEc/LpufnNs2nCZ8JmsjXty3bZEhRGZcpqbHpsrdCTzwRWnE0xbx3MOsIH63kJhHeGHcgIW8/dkGmBHcFCNiZflpUpgFV1iigVYKQg+VKOzGZupkVSLrtznEyM4kNJTT+3K6AM31p3Xvw8PQ/bpcUdJ5SxBG7l7D/+2X/6pk4ZSHvN/FM67pVYQZuIj52F1XZyNRcaZihMj5+PykVQZRrh2bJ4ATvJbyo/j3vIaunfMrqHRHBZR5iyH1+ONBSBacK5X/BQtJinnKWDTbaLACA/osPYqIRBa4WvOyr/ZKa/b4LbyjNjaDEphkfhthpoDZgAACGcCePfv9emdQALp8p+k+LATFZYycf2KCfvPcyr26ZtDwIJUhGUZWqMXGyVDn55pkYAvu422vjiHO+u7ybsaLPFkB/K82uc1r28QOkvcOeS4NMaOiHmEELAeT61GFu1ZgonR8YxbuNEH7r6hgb7spsoe63olugWqVUdFed7PxStEycE9HcZxVgkQnyX4KVkiIWyfXIS3VkgWvyv42lNTUd1Vqvog/mONm1kKRQOn0UfP0Zoz0LWqbogqRXzUmYaeej8jC25hrip1lQLyfdr46cxjAtW7uLxn1ajqzre032QPcnhTKrtwh/F7NCz0sowhc2SckZhJOzskkZbeXOb48lj0ev5q2b6IBN0QDVjjP5UoyHPwwu+yTQs5c6fYCfhhBqGm51OOTEIv61bAPb7URKkdHGrPo+9/QCJ/p9oOzX4sWz5W9V089KgHgZ4ySLVd1Y677Fu+fUrbzP3BfO//C84AetIiyi2exqsj2uETvXDEgpSeD+f/XZUC0fem/zws/oCPtipEZBTDChG8O0x7Q7ZL9iW6ywKviRb537h78NXyg9INGqfBpvZf2RM3MbwChZZgpdmBydHf3+87Dshya/ESvKGHJScGd2E/djImLLLXyhK4yFWmE9JP2dlYVkck6qcLhMzZPGwPLMtiZJIVnpw/Oex4ed5IhBnAOjPzRwv0tpgSeZ9yMONhaZXKhyz0fzTMM1q0Lpy6FzKQ8ZQ8cAbuhor2CFiG8FYIKTVbzbr7bUJ9QK63zNpULoPVlJS1wvZwxKh+klvSSuKNAg==");


        $mailBody = $helper->getMailBody($email, $message);
        try {
            $status = $graph->createRequest("POST", "/me/sendMail")
                ->attachBody($mailBody)
                ->execute();
        } catch (\Exception $e) {
            info($e->getMessage());
            nlog('microsoft_oauth_sending_failed' . $e->getMessage());
        }


    }

    public function __toString(): string
    {
        return 'microsoft_oauth';
    }
}
