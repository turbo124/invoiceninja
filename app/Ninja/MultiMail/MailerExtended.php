<?php

namespace App\Helpers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Mailer;
use Swift_Mailer;

class MailerExtended extends Mailer {
    /**
     * The name of this instance config.
     *
     * @var string
     */
    protected $configName;

    /**
     * Create a new Mailer instance.
     *
     * @param  \Illuminate\Contracts\View\Factory  $views
     * @param  \Swift_Mailer  $swift
     * @param  \Illuminate\Contracts\Events\Dispatcher|null  $events
     * @param  string  $configName
     * @return void
     */
    public function __construct(Factory $views, Swift_Mailer $swift, Dispatcher $events = null, $configName)
    {
        $this->views = $views;
        $this->swift = $swift;
        $this->events = $events;
        $this->configName = $configName;
    }

    /**
     * Queue a new e-mail message for sending.
     *
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @param  string|null  $queue
     * @return mixed
     */
    public function queue($view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        $config = $this->configName;

        return $this->queue->push('multimail@handleQueuedMessage', compact('view', 'config', 'data', 'callback'), $queue);
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @param  int  $delay
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        $config = $this->configName;

        return $this->queue->later($delay, 'multimail@handleQueuedMessage', compact('view', 'config', 'data', 'callback'), $queue);
    }

    public function forceReconnection() {
        parent::forceReconnection();
    }
}