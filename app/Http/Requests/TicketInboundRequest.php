<?php

namespace App\Http\Requests;

use App\Ninja\Tickets\Inbound\InboundTicketFactory;
use Illuminate\Support\Facades\Log;

class TicketInboundRequest extends Request
{
    public function entity()
    {
        $postmarkObject = new InboundTicketFactory(request()->input());

        Log::error(request()->input());
        Log::error($postmarkObject);
    }

    public function rules()
    {
        return [];
    }

    public function authorise()
    {
        return true;
    }
}
