<?php
namespace App\Helpers;

use Illuminate\Mail\Transport\MandrillTransport;
use GuzzleHttp\Client;
use Swift_SmtpTransport;
use Swift_Mailer;

use App;
use Config;

use ReflectionException;

class MultipleConfigMailer {
    public function __construct() {

    }

    public function using($name) {
        try {
            $mailerInstance = App::make("mailer.$name");
        } catch (ReflectionException $e) {
            $config = Config::get("multiple_config_mailer.{$name}");
            if (empty($config)) {
                throw new Exception("Could not find config '$name' for MultipleConfigMailer");
            }
            $this->createInstance($name, $config);
            $mailerInstance = App::make("mailer.$name");
        }

        return $mailerInstance;
    }

    protected function createInstance($name, $config) {
        App::singleton("mailer.$name", function ($app) use ($config) {
            $driverName = $config['driver'];
            switch ($driverName) {
                case 'smtp':
                    $driver = $this->createSmtpDriver($config);
                    break;
                case 'mandrill':
                    $driver = $this->createMandrillDriver($config);
                    break;
                default:
                    throw new Exception("Could not create a driver for name '$driverName'");
            }
            $swiftMailer = new Swift_Mailer($driver);

            // Once we have create the mailer instance, we will set a container instance
            // on the mailer. This allows us to resolve mailer classes via containers
            // for maximum testability on said classes instead of passing Closures.
            $mailer = new MailerExtended($app['view'], $swiftMailer, $app['events'], $name);

            $this->setMailerDependencies($mailer, $app);

            // If a "from" address is set, we will set it on the mailer so that all mail
            // messages sent by the applications will utilize the same "from" address
            // on each one, which makes the developer's life a lot more convenient.
            $from = $config['from'];

            if (is_array($from) && isset($from['address'])) {
                $mailer->alwaysFrom($from['address'], $from['name']);
            }

            if (isset($config['to'])) {
                $to = $config['to'];

                if (is_array($to) && isset($to['address'])) {
                    $mailer->alwaysTo($to['address'], $to['name']);
                }
            }

            // Here we will determine if the mailer should be in "pretend" mode for this
            // environment, which will simply write out e-mail to the logs instead of
            // sending it over the web, which is useful for local dev environments.
            $pretend = $config['pretend'];

            $mailer->pretend($pretend);

            return $mailer;
        });
    }

    protected function setMailerDependencies($mailer, $app) {
        $mailer->setContainer($app);

        if ($app->bound('Psr\Log\LoggerInterface')) {
            $mailer->setLogger($app->make('Psr\Log\LoggerInterface'));
        }

        if ($app->bound('queue')) {
            $mailer->setQueue($app['queue.connection']);
        }
    }

    protected function createSmtpDriver($config) {
        // The Swift SMTP transport instance will allow us to use any SMTP backend
        // for delivering mail such as Sendgrid, Amazon SES, or a custom server
        // a developer has available. We will just pass this configured host.
        $transport = Swift_SmtpTransport::newInstance(
            $config['host'], $config['port']
        );

        if (isset($config['encryption'])) {
            $transport->setEncryption($config['encryption']);
        }

        // Once we have the transport we will check for the presence of a username
        // and password. If we have it we will set the credentials on the Swift
        // transporter instance so that we'll properly authenticate delivery.
        if (isset($config['username'])) {
            $transport->setUsername($config['username']);

            $transport->setPassword($config['password']);
        }

        return $transport;
    }

    protected function createMandrillDriver($config) {
        $client = new Client;
        //$client = new Client();

        return new MandrillTransport($client, Config::get('services.mandrill.secret'));
    }

    /**
     * Handle a queued e-mail message job.
     *
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @param  array  $data
     * @return void
     */
    public function handleQueuedMessage($job, $data) {
        // The Mailer class wasn't designed to run as part of a long-running job
        // so we must force it to reconnect to the SMTP server instead of holding
        // a connection open.  This is less efficient than maintaining an open
        // connection, but prevents the connection from timing out and causing
        // the job to fail
        $this->using($data['config'])->handleQueuedMessage($job, $data);
        $this->using($data['config'])->forceReconnection();
    }
}