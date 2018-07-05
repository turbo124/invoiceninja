<?php

namespace App\Services;

use App\Ninja\Datatables\TicketTemplateDatatable;
use App\Ninja\Repositories\TicketTemplateRepository;

class TicketTemplateService extends BaseService
{
    /**
     * @var TicketTemplateRepository
     */

    protected $ticketTemplateRepo;

    /**
     * @var DatatableService
     */

    protected $datatableService;

    /**
     * TicketTemplateService constructor.
     * @param TicketTemplateRepository $ticketTemplateRepository
     * @param DatatableService $datatableService
     */

    public function __construct(TicketTemplateRepository $ticketTemplateRepository, DatatableService $datatableService)
    {

        $this->datatableService = $datatableService;
        $this->ticketTemplateRepo = $ticketTemplateRepository;

    }

    /**
     * @return TicketTemplateRepository
     */

    protected function getRepo()
    {

        return $this->ticketTemplateRepo;

    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */

    public function getDatatable()
    {

        $datatable = new TicketTemplateDatatable(false);

        $query = $this->ticketTemplateRepo->find();

            return $this->datatableService->createDatatable($datatable, $query);

    }
}
