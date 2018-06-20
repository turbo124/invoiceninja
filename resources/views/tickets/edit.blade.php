@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/jquery.datetimepicker.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/jquery.datetimepicker.css') }}" rel="stylesheet" type="text/css"/>
    <link href="https://netdna.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.css" rel="stylesheet">
    <script src="https://netdna.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.9/summernote.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.9/summernote.js"></script>v
@stop

<style>
    .td-left {width:1%; white-space:nowrap; text-align: right;}
    #accordion .ui-accordion-header {background: #033e5e; color: #fff;}
</style>

<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

@section('content')

    {!! Former::open($url)
            ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
            ->autocomplete('off')
            ->method($method)
            ->rules([
                'name' => 'required',
                'client_id' => 'required',
            ]) !!}

    @if ($ticket)
        {!! Former::populate($ticket) !!}
    @endif
    <!--
    <div class="panel panel-default">
        <table width="100%">
            <tr>
                <td width="50%">
                    <table class="table table-striped dataTable" >
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.ticket_number')!!}</td><td>{!! $ticket->id !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.status') !!}:</td><td>{!! $ticket->status->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.priority') !!}:</td><td>{!! $ticket->getPriorityName() !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.category') !!}:</td><td>{!! $ticket->category->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.due_date') !!}:</td><td>{!! $ticket->getDueDate() !!}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td width="50%">
                    <table class="table table-striped dataTable" >
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.subject')!!}:</td><td>{!! substr($ticket->subject, 0, 30) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.client') !!}:</td><td>{!! $ticket->client->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.contact') !!}:</td><td>{!! $ticket->getContactName() !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.created_at') !!}:</td><td>{!! \App\Libraries\Utils::fromSqlDateTime($ticket->created_at) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.last_updated') !!}:</td><td>{!! \App\Libraries\Utils::fromSqlDateTime($ticket->updated_at) !!}</td></tr>
                        <tr><td></td><td></td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    -->
    <div class="panel panel-default">
        <table width="100%">
            <tr>
                <td width="50%">
                    <table class="table table-striped dataTable" >
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.ticket_number')!!}</td><td>{!! $ticket->id !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.category') !!}:</td><td>{!! $ticket->category->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.subject')!!}:</td><td>{!! substr($ticket->subject, 0, 30) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.client') !!}:</td><td>{!! $ticket->client->name !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.contact') !!}:</td><td>{!! $ticket->getContactName() !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.assigned_to') !!}:</td>
                            <td>{!! $ticket->agent() !!} {!! Icon::create('random') !!}
                            </td>
                        </tr>
                        <tr><td></td><td></td></tr>
                        </tbody>
                    </table>
                </td>
                <td width="50%">
                    <table class="table table-striped dataTable" >
                        <tbody>
                        <tr><td class="td-left">{!! trans('texts.created_at') !!}:</td><td>{!! \App\Libraries\Utils::fromSqlDateTime($ticket->created_at) !!}</td></tr>
                        <tr><td class="td-left">{!! trans('texts.last_updated') !!}:</td><td>{!! \App\Libraries\Utils::fromSqlDateTime($ticket->updated_at) !!}</td></tr>
                        <tr ><td class="td-left">{!! trans('texts.due_date') !!}:</td>
                            <td>
                                <input id="due_date" type="text" data-bind="dateTimePicker"
                                       class="form-control time-input time-input-end" placeholder="{{ trans('texts.due_date') }}" value="{{ $ticket->getDueDate() }}"/>
                            </td>
                        </tr>
                        <tr><td class="td-left">{!! trans('texts.status') !!}:</td>
                            <td>
                                {!! Former::select('status_id')->label('')
                                ->fromQuery($ticket->getAccountStatusArray(), 'name', 'id') !!}
                            </td>
                        </tr>
                        <tr><td class="td-left">{!! trans('texts.priority') !!}:</td>
                            <td>
                                {!! Former::select('priority_id')->label('')
                                ->fromQuery($ticket->getPriorityArray(), 'name', 'id') !!}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="panel-default ui-accordion ui-widget ui-helper-reset" id="accordion" role="tablist">
        @foreach($ticket->comments as $comment)
        <h3 class="ui-accordion-header ui-corner-top ui-state-default ui-accordion-header-active ui-state-active ui-accordion-icons" role="tab" id="accordion">{!! $comment->getCommentHeader() !!}</h3>
        <div>
            <p>
               {!! $comment->description !!}
            </p>
        </div>
       @endforeach
    </div>

    <div id="summernote" style="margin-top: 30px;"><p>Hello Summernote</p></div>

    <div class="panel-default" style="margin-top:30px; width: 100%; padding-bottom: 0px !important">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.reply') !!}</h3>
        </div>

        <div class="panel-body">
            {!! Former::textarea('ticket_comments[description]')->label(null)->style('width: 100%')->rows(10) !!}
        </div>

    </div>


    <center class="center">
        <span class="btn-group" style="padding-right:8px; padding-left:14px;">
            {!! DropdownButton::normal(trans('texts.reply'))
            ->withContents([
            ['label'=>trans('reply and close'),'url'=>'tickets/sdsds'],
            ])
            ->large()
            ->dropup() !!}
        </span>
    </center>

    <div role="tabpanel" class="panel-default" style="margin-top:30px;">

        <ul class="nav nav-tabs" role="tablist" style="border: none">
            <li role="presentation" class="active"><a href="#linked_objects" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.linked_objects") }}</a></li>
            <li role="presentation"><a href="#private_notes" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.private_notes") }}</a></li>
            <li role="presentation"><a href="#tags" aria-controls="footer" role="tab" data-toggle="tab">{{ trans("texts.tags") }}</a></li>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <li role="presentation"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                        {{ trans("texts.documents") }}
                    </a></li>
            @endif
        </ul>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

        <div class="tab-content" style="padding-right:12px;max-width:600px;">
            <div role="tabpanel" class="tab-pane active" id="public_notes" style="padding-bottom:44px;">
                {!! Former::textarea('public_notes')
                        ->data_bind("value: public_notes, valueUpdate: 'afterkeydown'")
                        ->label(null)->style('width: 100%')->rows(4)->label(null) !!}
            </div>
            <div role="tabpanel" class="tab-pane" id="private_notes" style="padding-bottom:44px">
                {!! Former::textarea('private_notes')
                        ->data_bind("value: private_notes, valueUpdate: 'afterkeydown'")
                        ->label(null)->style('width: 100%')->rows(4) !!}
            </div>
            <div role="tabpanel" class="tab-pane" id="terms">
                {!! Former::textarea('terms')
                        ->data_bind("value:terms, placeholder: terms_placeholder, valueUpdate: 'afterkeydown'")
                        ->label(false)->style('width: 100%')->rows(4)
                        ->help('<div class="checkbox">
                                    <label>
                                        <input name="set_default_terms" type="checkbox" style="width: 16px" data-bind="checked: set_default_terms"/>'.trans('texts.save_as_default_terms').'
                                    </label>
                                    <div class="pull-right" data-bind="visible: showResetTerms()">
                                        <a href="#" onclick="return resetTerms()" title="'. trans('texts.reset_terms_help') .'">' . trans("texts.reset_terms") . '</a>
                                    </div>
                                </div>') !!}
            </div>
            <div role="tabpanel" class="tab-pane" id="footer">
                {!! Former::textarea('invoice_footer')
                        ->data_bind("value:invoice_footer, placeholder: footer_placeholder, valueUpdate: 'afterkeydown'")
                        ->label(false)->style('width: 100%')->rows(4)
                        ->help('<div class="checkbox">
                                    <label>
                                        <input name="set_default_footer" type="checkbox" style="width: 16px" data-bind="checked: set_default_footer"/>'.trans('texts.save_as_default_footer').'
                                    </label>
                                    <div class="pull-right" data-bind="visible: showResetFooter()">
                                        <a href="#" onclick="return resetFooter()" title="'. trans('texts.reset_footer_help') .'">' . trans("texts.reset_footer") . '</a>
                                    </div>
                                </div>') !!}
            </div>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative;z-index:9">
                    <div id="document-upload">

                    </div>
                </div>
            @endif
        </div>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}

    </div>

        {!! Former::close() !!}

    <script type="text/javascript">
        $( function() {
            $( "#accordion" ).accordion();
        } );

        // Add moment support to the datetimepicker
        Date.parseDate = function( input, format ){
            return moment(input, format).toDate();
        };
        Date.prototype.dateFormat = function( format ){
            return moment(this).format(format);
        };

        jQuery('#due_date').datetimepicker({
            lazyInit: true,
            validateOnBlur: false,
            step: '{{ env('TASK_TIME_STEP', 15) }}',
            value: '{{ $ticket->getDueDate() }}',
            format: '{{ $datetimeFormat }}',
            formatDate: '{{ $account->getMomentDateFormat() }}',
            formatTime: '{{ $account->military_time ? 'H:mm' : 'h:mm A' }}',
        });


        $(document).ready(function() {
            $('#summernote').summernote();
        });
    </script>

@stop
