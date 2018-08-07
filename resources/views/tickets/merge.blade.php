@extends('header')

@section('head')
    @parent
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

@stop

<style>
</style>

@section('content')

    {!! Former::open($url)
        ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
        ->autocomplete('off')
        ->method($method)
        ->rules([
            'parent' => 'required',
        ]) !!}

    @if ($ticket)
        {!! Former::populate($ticket) !!}
    @endif


    <div class="panel panel-default">

        <div class="panel-heading">
            <h3 class="panel-title">{{ trans('texts.ticket_merge') }}</h3>
        </div>

        <div class="panel-body">

            <div class="row">

                <div class="col-md-3">
                    <h3>{{ trans('texts.ticket_number') }} {!! $ticket->ticket_number !!}</h3>
                </div>

                <div class="col-md-9">
                    <b> {!! $ticket->client->name !!}</b>
                    <br>
                    {!! $ticket->subject !!}
                    <br>
                    {!! \App\Libraries\Utils::fromSqlDateTime($ticket->created_at) !!}
                </div>

            </div>

            <div class="row">

                <div class="col-md-3">
                </div>

                <div class="col-md-9">

                {!! Former::textarea('closing_note')
                            ->label('')
                            ->help('This ticket will be closed with the following comment')
                            !!}

                </div>

            </div>

        </div>

    </div>

    <div style="text-align: center; width: 100%;">
    <hr>
        <b> {{ trans('texts.merge_placeholder', ['ticket' =>$ticket->ticket_number]) }}</b>
    <hr>
    </div>

    <div class="panel panel-default">

        <div class="panel-heading">
            <h3 class="panel-title">{{ trans('texts.select_ticket') }}</h3>
        </div>

        <div class="panel-body">

            <div class="row">

                <div class="col-md-3">
                {!! trans('texts.ticket_number') !!}
                </div>

                <div class="col-md-9">

                    {!! Former::select('parent')
                            ->label('')
                            ->help('Select ticket to merge into')
                            ->addOption('', '')
                            ->data_bind("dropdown: merge, dropdownOptions: {highlighter: comboboxHighlighter}")
                            ->addClass('pull-right')
                            ->addGroupClass('') !!}

                    {!! Former::textarea('updating_note')
                                ->label('')
                                ->help('This ticket will be updated with the following comment')
                                !!}

                </div>

            </div>

            <div role="tabpanel" class="tab-pane" id="merge" style="padding-bottom:44px">

                <span class="pull-right">{!! Button::warning(trans('texts.merge'))->withAttributes(['onclick' => 'submitAction()']) !!}</span>

            </div>

        </div>

    </div>

    {!! Former::close() !!}


    <script type="text/javascript">

    <!-- Init mergeable tickets -->
    @if($mergeableTickets)
        var mergeableTickets = {!! $mergeableTickets !!};
        var ticketMap = {};
        var $ticketSelect = $('select#parent');
        var parentTicketId = false;

        $(function() {
            for(var i=0; i<mergeableTickets.length; i++){
                var ticket = mergeableTickets[i];
                ticketMap[ticket.public_id] = ticket;
                $ticketSelect.append(new Option(' # ' + ticket.ticket_number + ' :: ' + ticket.subject, ticket.public_id));
            }


            //harvest and set the client_id and contact_id here
            var $input = $('select#parent');
            $input.combobox().on('change', function(e) {
                var selectedTicketid = parseInt($('input[name=merge]').val(), 10) || 0;

                if (selectedTicketid > 0) {
                    parentTicketId = selectedTicketid;
                }
            });
        });
    @endif


    function submitAction() {

        $('.main-form').submit();

    }









    var ViewModel = function(data) {
        var self = this;

        self.merged_parent_ticket_id = ko.observable();
        self.old_ticket_comment = ko.observable();
        self.updated_ticket_comment = ko.observable();
        self.ticket = 
    }
</script>


@stop