<?php
namespace App\Services;
use App\Jobs\Ticket\TicketSendNotificationEmail;
use App\Ninja\Datatables\TicketDatatable;
use App\Ninja\Repositories\TicketRepository;

/**
 * Class ticketService.
 */
class TicketService extends BaseService
{

    /**
     * @var TicketRepository
     */

    protected $ticketRepo;

    /**
     * @var DatatableService
     */

    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param ticketRepository $ticketRepo
     * @param DatatableService  $datatableService
     */

    public function __construct(TicketRepository $ticketRepo, DatatableService $datatableService)
    {

        $this->ticketRepo = $ticketRepo;
        $this->datatableService = $datatableService;

    }

    /**
     * @return TicketRepository
     */

    protected function getRepo()
    {

        return $this->ticketRepo;

    }

    /**
     * @param $data
     * @param mixed $ticket
     *
     * @return mixed|null
     */

    public function save($data, $ticket = false)
    {

        $this->processTicket($data, $ticket);

            return $this->ticketRepo->save($data, $ticket);

    }

    /**
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */

    public function getDatatable($search)
    {
        // we don't support bulk edit and hide the client on the individual client page
        $datatable = new TicketDatatable();

        $query = $this->ticketRepo->find($search);

            return $this->datatableService->createDatatable($datatable, $query);

    }

    /**
     * @param $data
     * @param $ticket
     */

    private function processTicket($data, $ticket)
    {

        /* If comment added to ticket fire notifications */

        if(strlen($data['comment']) >= 1)
            $this->dispatch(new TicketSendNotificationEmail($data, $ticket));
    }

}