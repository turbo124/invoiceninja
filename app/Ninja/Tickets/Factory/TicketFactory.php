<?php

namespace App\Ninja\Tickets\Factory;

use App\Models\Ticket;

/**
 * Class TicketFactory.
 */
class TicketFactory
{
    /**
     * @var Ticket
     */
    protected $originalTicket;

    /**
     * @var Ticket
     */
    protected $updatedTicket;

    /**
     * @var array
     */
    protected $changedAttributes;

    /**
     * @var
     */
    protected $action;

    /**
     * TicketFactory constructor.
     */
    public function __construct($originalTicket, $changedAttributes, Ticket $updatedTicket, $action)
    {
        $this->originalTicket = $originalTicket;
        $this->changedAttributes = $changedAttributes;
        $this->updatedTicket = $updatedTicket;
        $this->action = $action;
    }

    /**
     * Public entry point.
     */
    public function process()
    {
        $classEntity = "App\Ninja\Tickets\Actions\\".ucfirst(camel_case($this->action));

        $handler = new $classEntity();
        $handler->fire($this->updatedTicket);
    }
}
