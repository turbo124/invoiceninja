<?php

namespace App\Http\Requests;

use App\Ninja\Tickets\Inbound\InboundTicketFactory;
use Illuminate\Support\Facades\Log;

class TicketInboundRequest extends Request
{
    public function entity()
    {
        $postmarkObject = new InboundTicketFactory(request()->getContent());

        Log::error(request()->getContent());
        Log::error($postmarkObject);
    }

    public function rules()
    {
        return [];
    }

    public function authorize()
    {
        return true;
    }
}
