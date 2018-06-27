@extends('header')

@section('content')
    @parent

    <style type="text/css">

        #logo {
            padding-top: 6px;
        }

    </style>

    {!! Former::open_for_files()
            ->addClass('warn-on-exit')
            ->autocomplete('on')
            ->rules([
                'name' => 'required',
            ]) !!}

    {{ Former::populate($account_ticket_settings) }}

    @include('accounts.nav', ['selected' => ACCOUNT_TICKETS])

    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.details') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                    {!! Former::text('local_part') !!}


                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.address') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">



                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.defaults') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">



                </div>
            </div>
        </div>


    </div>

    <center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}


@stop
