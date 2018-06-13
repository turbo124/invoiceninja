<?php

namespace App\Services;

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
     * @param ticketRepository $creditRepo
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
        return $this->ticketRepo->save($data, $ticket);
    }

    /**
     * @param $ticketPublicId
     * @param $search
     * @param mixed $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search, $userId)
    {
        // we don't support bulk edit and hide the client on the individual client page
        $datatable = new TicketDatatable();

        $query = $this->ticketRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
