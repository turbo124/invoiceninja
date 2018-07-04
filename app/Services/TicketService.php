<?php
namespace App\Services;
use App\Jobs\Ticket\TicketSendNotificationEmail;
use App\Ninja\Datatables\TicketDatatable;
use App\Ninja\Datatables\TicketTemplateDatatable;
use App\Ninja\Repositories\TicketRepository;
use App\Ninja\Repositories\TicketTemplateRepository;

/**
 * Class ticketService.
 */
class TicketService extends BaseService
{

    protected $ticketTemplateRepo;

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
    public function __construct(TicketRepository $ticketRepo, TicketTemplateRepository $ticketTemplateRepository, DatatableService $datatableService)
    {
        $this->ticketRepo = $ticketRepo;
        $this->datatableService = $datatableService;
        $this->ticketTemplateRepo = $ticketTemplateRepository;
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

    public function getDatatable($search)
    {
        // we don't support bulk edit and hide the client on the individual client page
        $datatable = new TicketDatatable();
        $query = $this->ticketRepo->find($search);
        return $this->datatableService->createDatatable($datatable, $query);
    }

    public function getTemplateDatatable()
    {
        $datatable = new TicketTemplateDatatable();
        $query = $this->ticketTemplateRepo->find();
        return $this->datatableService->createDatatable($datatable, $query);

    }

    private function processTicket($data, $ticket) {
        /* If comment added to ticket fire notifications */
        if(strlen($data['comment']) >= 1)
            $this->dispatch(new TicketSendNotificationEmail($data, $ticket));
    }
}