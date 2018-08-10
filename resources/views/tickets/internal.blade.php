@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/jquery.datetimepicker.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/jquery.datetimepicker.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

@stop

<style>
    .td-left {width:1%; white-space:nowrap; text-align: right; height:40px;}
    .td-right {width:1%; white-space:nowrap; text-align: left; height:40px;}
    #accordion .ui-accordion-header {background: #033e5e; color: #fff;}
</style>

@section('content')

    {!! Former::open($url)
            ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
            ->autocomplete('off')
            ->method($method)
            ->rules([
                'user_id' => 'required',
                'description' => 'required',
                'subject' => ' required',
            ]) !!}

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
        {!! Former::hidden('category_id')->value(1) !!}
        {!! Former::hidden('parent_ticket_id')->value($parent_ticket->public_id) !!}
        {!! Former::hidden('status_id')->value(1) !!}
    </div>

    <div style="display:none">
        {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
    </div>

    <div class="panel panel-default">

        <div class="panel-heading">
            <h3 class="panel-title">{{ trans('texts.new_internal_ticket') }}</h3>
        </div>

        <div class="panel-body">
        </div>

    </div>

    {!! Former::close() !!}





    <script type="text/javascript">
    </script>

@stop