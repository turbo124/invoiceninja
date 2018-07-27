<?php

namespace App\Ninja\Tickets\Deltas;

use App\Models\Ticket;

/**
 * Class DeltaFactory
 * @package App\Ninja\Tickets\Deltas
 */
class DeltaFactory
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
     * DeltaFactory constructor.
     */
    public function __construct(array $originalTicket, array $changedAttributes, $updatedTicket)
    {
        $this->originalTicket = $originalTicket;
        $this->changedAttributes = $changedAttributes;
        $this->updatedTicket = $updatedTicket;
    }

    /**
     * Public entry point
     */
    public function process()
    {
        foreach($this->changedAttributes as $attribute)
            $this->performDeltaAction($attribute);
    }


    /**
     * @param $modelAttribute
     *
     * Possible actions.
     *
     * 1. New ticket response (to client)
     * 2. Updated ticket (to client)
     * 3. Assign ticket (to agent)
     * 4. Close ticket (to client)
     * 5. Ticket overdue (to agent)
     *
     */
    private function performDeltaAction($modelAttribute)
    {
        switch($modelAttribute)
        {
            case 'agent_id':
                AgentDelta::agentTicketChange($this->updatedTicket, $this->originalTicket);
            break;

        }
    }


}