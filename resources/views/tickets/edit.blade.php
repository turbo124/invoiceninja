@extends('header')

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

    <div role="tabpanel" class="pull-left" style="margin-left:40px; margin-top:30px;">

        <ul class="nav nav-tabs" role="tablist" style="border: none">
            <li role="presentation" class="active"><a href="#public_notes" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.public_notes') }}</a></li>
            <li role="presentation"><a href="#private_notes" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.private_notes") }}</a></li>
            <li role="presentation"><a href="#terms" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.terms") }}</a></li>
            <li role="presentation"><a href="#footer" aria-controls="footer" role="tab" data-toggle="tab">{{ trans("texts.footer") }}</a></li>
            @if ($account->hasFeature(FEATURE_DOCUMENTS))
                <li role="presentation"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                        {{ trans("texts.documents") }}
                        @if ($count = ($invoice->countDocuments($expenses)))
                            ({{ $count }})
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
                        <div class="dropzone">
                            <div data-bind="foreach: documents">
                                <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                            </div>
                        </div>
                        @if ($invoice->hasExpenseDocuments() || $expenses->count())
                            <h4>{{trans('texts.documents_from_expenses')}}</h4>
                            @foreach($invoice->expenses as $expense)
                                @if ($expense->invoice_documents)
                                    @foreach($expense->documents as $document)
                                        <div>{{$document->name}}</div>
                                    @endforeach
                                @endif
                            @endforeach
                            @foreach($expenses as $expense)
                                @if ($expense->invoice_documents)
                                    @foreach($expense->documents as $document)
                                        <div>{{$document->name}}</div>
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}

    </div>

        {!! Former::close() !!}

    <script>


    </script>

@stop
