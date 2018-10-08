<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Ninja\Tickets\Actions\TicketOverdue;

/**
 * Class SendOverdueTicketNotification.
 */
class SendOverdueTicketNotification extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Ticket
     */
    protected $ticket;

    /**
     * @var string
     */
    protected $server;

    /**
     * Create a new job instance.
     *
     * @param Ticket $ticket
     * @param mixed  $type
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->server = config('database.default');
    }

    /**
     * Execute the job.
     *
     * @param TicketOverdue $ticketOverdue
     */
    public function handle(TicketOverdue $ticketOverdue)
    {
        $ticketOverdue->fire($this->ticket);
    }
}
