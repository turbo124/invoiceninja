@extends('header')

@section('content')
    @parent

    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    {!! Former::open_for_files()
            ->addClass('warn-on-exit')
            ->autocomplete('on')
            ->rules([])
    !!}

    {{ Former::populate($ticket_templates) }}

    @include('accounts.nav', ['selected' => ACCOUNT_TICKETS])

    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.add_template') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                    {!! Former::text('name')
                             ->label(trans('texts.name'))
                    !!}


                    {!! Former::textarea('name')
                             ->label(trans('texts.description'))
                             ->style('width: 100%')
                             ->rows(10)
                    !!}
                </div>
                <!--
                <div class="panel-body">
                    {!! Former::textarea('description')->style('display:none')->label('texts.description')->raw() !!}
                    <div id="descriptionEditor" class="form-control" style="min-height:160px" onclick="focusEditor()"></div>
                    <div class="pull-right" style="padding-top:10px;text-align:right">
                        {!! Button::normal(trans('texts.raw'))->withAttributes(['onclick' => 'showRaw()'])->small() !!}
                    </div>
                    @include('partials/quill_toolbar', ['name' => 'description'])
                </div>
                -->
            </div>

        </div>
    </div>

    <div class="modal fade" id="rawModal" tabindex="-1" role="dialog" aria-labelledby="rawModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="width:800px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="rawModalLabel">{{ trans('texts.raw_html') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                    <div class="panel panel-default">
                        <div class="modal-body">
                            <textarea id="raw-textarea" rows="20" style="width:100%"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
                    <button type="button" onclick="updateRaw()" class="btn btn-success" data-dismiss="modal">{{ trans('texts.update') }}</button>
                </div>
            </div>
        </div>
    </div>

    <center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

<script>
    var editor = false;
    $(function() {
        editor = new Quill('#descriptionEditor', {
            modules: {
                'toolbar': { container: '#descriptionToolbar' },
                'link-tooltip': true
            },
            theme: 'snow'
        });
        editor.setHTML($('#descriptionEditor').val());
        editor.on('text-change', function(delta, source) {
            if (source == 'api') {
                return;
            }
            var html = editor.getHTML();
            $('#descriptionEditor').val(html);
            NINJA.formIsChanged = true;
        });
    });

    function focusEditor() {
        editor.focus();
    }

    function showRaw() {
        var description = $('#descriptionEditor').val();
        $('#raw-textarea').val(formatXml(description));
        $('#rawModal').modal('show');
    }

    function updateRaw() {
        var value = $('#raw-textarea').val();
        editor.setHTML(value);
        $('#descriptionEditor').val(value);
    }
</script>
@stop
