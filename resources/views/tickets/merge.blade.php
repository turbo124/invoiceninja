@extends('header')

@section('head')
    @parent
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

@stop

<style>
</style>

@section('content')



    <div role="tabpanel" class="tab-pane" id="merge" style="padding-bottom:44px">
        <div class="alert alert-warning">{{ trans('texts.merge_prompt') }}</div>

        <div>
            {!! Former::select('merge')
                    ->label('')
                    ->addOption('', '')
                    ->data_bind("dropdown: merge, dropdownOptions: {highlighter: comboboxHighlighter}")
                    ->addClass('pull-right')
                    ->addGroupClass('') !!}

            <span class="pull-right">{!! Button::warning(trans('texts.merge'))->withAttributes(['onclick' => 'mergeEmailAction()']) !!}</span>

        </div>

    </div>

@stop

<script type="javascript">
    function mergeEmailAction(){

        if(parentTicketId > 0) {
            //window.location.href = "/tickets/merge/{{$ticket->public_id}}/" + parentTicketId;
            $(location).attr('href', "/tickets/merge/{{$ticket->public_id}}/" + parentTicketId)

        }
    }

    <!-- Init mergeable tickets -->
    @if($mergeableTickets)
    var mergeableTickets = {!! $mergeableTickets !!};
    var ticketMap = {};
    var $ticketSelect = $('select#merge');
    var parentTicketId = false;

    $(function() {
        for(var i=0; i<mergeableTickets.length; i++){
            var ticket = mergeableTickets[i];
            ticketMap[ticket.public_id] = ticket;
            $ticketSelect.append(new Option(' # ' + ticket.ticket_number + ' :: ' + ticket.subject, ticket.public_id));
        }


        //harvest and set the client_id and contact_id here
        var $input = $('select#merge');
        $input.combobox().on('change', function(e) {
            var selectedTicketid = parseInt($('input[name=merge]').val(), 10) || 0;

            if (selectedTicketid > 0) {
                parentTicketId = selectedTicketid;
            }
        });
    });
    @endif

</script>