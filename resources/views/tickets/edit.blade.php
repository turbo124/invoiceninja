@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/jquery.datetimepicker.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/jquery.datetimepicker.css') }}" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

@stop

<style>
    .td-left {width:1%; white-space:nowrap; text-align: right;}
    #accordion .ui-accordion-header {background: #033e5e; color: #fff;}
</style>

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

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
    </div>
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

    <div class="panel-default" style="margin-top:30px; width: 100%; padding-bottom: 0px !important">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.reply') !!}</h3>
        </div>

        <div class="panel-body">
            {!! Former::textarea('ticket_comments[description]')->label(null)->style('width: 100%')->rows(10) !!}
        </div>

    </div>

    <div class="row">
        <center class="buttons">
            {!! DropdownButton::normal(trans('texts.more_actions'))
            ->withContents([
            ['label'=>trans('texts.ticket_split'),'url'=>'tickets/sdsds'],
            ['label'=>trans('texts.ticket_merge'),'url'=>'tickets/sdsds'],
            ['label'=>trans('texts.mark_spam'),'url'=>'tickets/sdsds'],
            ])
            ->large()
            ->dropup() !!}
            {!! Button::danger(trans('texts.ticket_close'))->large() !!}
            {!! Button::primary(trans('texts.ticket_update'))->large()->withAttributes(['onclick' => 'submitAction()']) !!}
        </center>
    </div>

    <div role="tabpanel" class="panel-default" style="margin-top:30px;">

        <ul class="nav nav-tabs" role="tablist" style="border: none">
            <li role="presentation" class="active"><a href="#linked_objects" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.linked_objects") }}</a></li>
            <li role="presentation"><a href="#private_notes" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.private_notes") }}</a></li>
            <li role="presentation"><a href="#tags" aria-controls="footer" role="tab" data-toggle="tab">{{ trans("texts.tags") }}</a></li>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <li role="presentation"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                        {{ trans("texts.documents") }}
                        @if ($ticket->documents()->count() >= 1)
                            ({{ $ticket->documents()->count() }})
                        @endif
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

            </div>
            <div role="tabpanel" class="tab-pane" id="footer">

            </div>
                <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative;z-index:9">
                    <div id="document-upload">
                        <div class="dropzone">
                            <div data-bind="foreach: documents">
                                <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                            </div>
                        </div>
                        @if ($ticket->documents())
                            @foreach($ticket->documents() as $document)
                                <div>{{$document->name}}</div>
                            @endforeach
                        @endif
                    </div>
                </div>
        </div>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}

    </div>

        {!! Former::close() !!}

    <script type="text/javascript">

        <!-- Initialize ticket_comment accordion -->
        $( function() {
            $( "#accordion" ).accordion();

            window.model = new ViewModel({!! $ticket !!});

            ko.applyBindings(model);

            @include('partials.dropzone', ['documentSource' => 'model.documents()'])

        } );

        // Add moment support to the datetimepicker
        Date.parseDate = function( input, format ){
            return moment(input, format).toDate();
        };
        Date.prototype.dateFormat = function( format ){
            return moment(this).format(format);
        };

        <!-- Initialize date time picker for due date -->
        jQuery('#due_date').datetimepicker({
            lazyInit: true,
            validateOnBlur: false,
            step: '{{ env('TASK_TIME_STEP', 15) }}',
            value: '{{ $ticket->getDueDate() }}',
            format: '{{ $datetimeFormat }}',
            formatDate: '{{ $account->getMomentDateFormat() }}',
            formatTime: '{{ $account->military_time ? 'H:mm' : 'h:mm A' }}',
        });


        <!-- Initialize drop zone file uploader -->
        $('.main-form').submit(function(){
            if($('#document-upload .dropzone .fallback input').val())$(this).attr('enctype', 'multipart/form-data')
            else $(this).removeAttr('enctype')
        })

            var ViewModel = function (data) {
                var self = this;

                self.documents = ko.observableArray();

                self.mapping = {
                    'documents': {
                        create: function (options) {
                            return new DocumentModel(options.data);
                        }
                    }
                }

                if (data) {
                    ko.mapping.fromJS(data, self.mapping, this);
                }

                self.addDocument = function() {
                    var documentModel = new DocumentModel();
                    self.documents.push(documentModel);
                    return documentModel;
                }

                self.removeDocument = function(doc) {
                    var public_id = doc.public_id?doc.public_id():doc;
                    self.documents.remove(function(document) {
                        return document.public_id() == public_id;
                    });
                }
            };


        function DocumentModel(data) {
            var self = this;
            self.public_id = ko.observable(0);
            self.size = ko.observable(0);
            self.name = ko.observable('');
            self.type = ko.observable('');
            self.url = ko.observable('');

            self.update = function(data){
                ko.mapping.fromJS(data, {}, this);
            }

            if (data) {
                self.update(data);
            }
        }

        function addDocument(file) {
            file.index = model.documents().length;
            model.addDocument({name:file.name, size:file.size, type:file.type});
        }

        function addedDocument(file, response) {
            model.documents()[file.index].update(response.document);
        }

        function deleteDocument(file) {
            model.removeDocument(file.public_id);
        }

        function submitAction() {
            $('.main-form').submit();
        }



    </script>

@stop
