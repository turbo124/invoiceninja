@extends('header')

@section('content')
    @parent


    {!! Former::open_for_files()
            ->addClass('warn-on-exit')
            ->autocomplete('on')
            ->rules([])
    !!}

    {{ Former::populate($account_ticket_settings) }}
    {{ Former::populateField('local_part', $account_ticket_settings->local_part) }}

    @include('accounts.nav', ['selected' => ACCOUNT_TICKETS])

    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.defaults') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                    {!! Former::text('ticket_number_start')
                             ->label(trans('texts.counter'))
                            ->help('ticket_number_start_help')
                            !!}

                    <div id="">
                        {!! Former::select('default_priority')
                            ->text(trans('texts.default_priority'))
                            ->options([
                            TICKET_PRIORITY_LOW => trans('texts.low'),
                            TICKET_PRIORITY_MEDIUM => trans('texts.medium'),
                            TICKET_PRIORITY_HIGH => trans('texts.high'),
                        ])
                         !!}
                    </div>

                    <div id="">
                        {!! Former::select('ticket_master')
                            ->text(trans('texts.ticket_master'))
                            ->help(trans('texts.ticket_master_help'))
                            ->fromQuery($account->users, 'displayName', 'id')
                         !!}
                    </div>

                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.domain') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                        {!! Former::text('local_part')
                                ->placeholder('texts.local_part_placeholder')
                                ->label(trans('texts.local_part'))
                                ->help('local_part_help') !!}

                    {!! Former::text('from_name')
                            ->placeholder('texts.from_name_placeholder')
                            ->label(trans('texts.from_name')) !!}

                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.attachments') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                    {!! Former::checkbox('client_upload')
                        ->text(trans('texts.enable'))
                        ->help(trans('texts.enable_client_upload_help'))
                        ->label(trans('texts.client_upload'))
                        ->value(1) !!}

                    <div id="max_file_size">
                        {!! Former::select('max_file_size')
                            ->text(trans('texts.max_file_size'))
                            ->fromQuery($account_ticket_settings->max_file_sizes())
                        ->help(trans('texts.max_file_size_help'))
                         !!}
                    </div>


                    {!! Former::text('mime_types')
                        ->placeholder('texts.mime_types_placeholder')
                        ->label(trans('texts.mime_types'))
                        ->help('mime_types_help') !!}

                </div>

            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.notifications') !!}</h3>
                </div>
                <div class="panel-body form-padding-right">

                    <div id="">
                        {!! Former::select('new_ticket_template_id')
                            ->text(trans('texts.new_ticket_template_id'))
                            ->options([
                            AUTO_BILL_OFF => trans('texts.off'),
                            AUTO_BILL_OPT_IN => trans('texts.opt_in'),
                            AUTO_BILL_OPT_OUT => trans('texts.opt_out'),
                            AUTO_BILL_ALWAYS => trans('texts.always'),
                        ])
                        ->help(trans('texts.new_ticket_autoresponder_help'))
                         !!}
                    </div>

                    <div id="">
                        {!! Former::select('update_ticket_template_id')
                            ->text(trans('texts.update_ticket_template_id'))
                            ->options([
                            AUTO_BILL_OFF => trans('texts.off'),
                            AUTO_BILL_OPT_IN => trans('texts.opt_in'),
                            AUTO_BILL_OPT_OUT => trans('texts.opt_out'),
                            AUTO_BILL_ALWAYS => trans('texts.always'),
                        ])
                        ->help(trans('texts.update_ticket_autoresponder_help'))
                         !!}
                    </div>

                    <div id="">
                        {!! Former::select('close_ticket_template_id')
                            ->text(trans('texts.close_ticket_template_id'))
                            ->options([
                            AUTO_BILL_OFF => trans('texts.off'),
                            AUTO_BILL_OPT_IN => trans('texts.opt_in'),
                            AUTO_BILL_OPT_OUT => trans('texts.opt_out'),
                            AUTO_BILL_ALWAYS => trans('texts.always'),
                        ])
                        ->help(trans('texts.close_ticket_autoresponder_help'))
                         !!}
                    </div>


                    {!! Former::checkbox('alert_new_ticket')
                        ->text(trans('texts.enable'))
                        ->label(trans('texts.alert_new_ticket'))
                        ->value(1) !!}

                    {!! Former::text('alert_new_ticket_email')
                        ->placeholder('texts.comma_separated_values')
                        ->label(trans('texts.new_ticket_notification_list'))
                        ->help('alert_new_ticket_email_help') !!}

                    {!! Former::checkbox('alert_new_comment')
                       ->text(trans('texts.enable'))
                       ->label(trans('texts.alert_comment_ticket'))
                       ->value(1) !!}

                    {!! Former::text('alert_new_comment_email')
                        ->placeholder('texts.comma_separated_values')
                        ->label(trans('texts.update_ticket_notification_list'))
                        ->help('alert_comment_ticket_email_help') !!}

                    {!! Former::checkbox('alert_ticket_assign_agent')
                       ->text(trans('texts.enable'))
                       ->label(trans('texts.alert_ticket_assign_agent'))
                       ->value(1) !!}

                    {!! Former::text('alert_ticket_assign_email')
                        ->placeholder('texts.comma_separated_values')
                        ->label(trans('texts.alert_ticket_assign_agent_notifications'))
                        ->help('alert_ticket_assign_agent_help') !!}

                    {!! Former::checkbox('alert_ticket_transfer_agent')
                      ->text(trans('texts.enable'))
                      ->label(trans('texts.alert_ticket_transfer_agent'))
                      ->value(1) !!}

                    {!! Former::text('alert_ticket_transfer_email')
                        ->placeholder('texts.comma_separated_values')
                        ->label(trans('texts.alert_ticket_transfer_email'))
                        ->help('alert_ticket_transfer_email_help') !!}

                    {!! Former::checkbox('alert_ticket_overdue_agent')
                          ->text(trans('texts.enable'))
                          ->label(trans('texts.alert_ticket_overdue_agent'))
                          ->value(1) !!}

                    {!! Former::text('alert_ticket_overdue_email')
                        ->placeholder('texts.comma_separated_values')
                        ->label(trans('texts.alert_ticket_overdue_email'))
                        ->help('alert_ticket_overdue_email_help') !!}

                    {!! Former::checkbox('show_agent_details')
                       ->text(trans('texts.enable'))
                       ->label(trans('texts.show_agent_details'))
                       ->value(1) !!}

                </div>
            </div>


            <center>
                {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
            </center>

            {!! Button::primary(trans('texts.add_template'))
            ->asLinkTo(URL::to('/ticket_template/create'))
            ->withAttributes(['class' => 'pull-right'])
            ->appendIcon(Icon::create('plus-sign')) !!}
        </div>
        {!! Former::close() !!}
    </div>

        @include('partials.bulk_form', ['entityType' => ENTITY_TICKET_TEMPLATE])

            {!! Datatable::table()
              ->addColumn(
                trans('texts.name'),
                trans('texts.description'),
                trans('texts.action'))
              ->setUrl(url('api/ticket_templates/'))
              ->setOptions('sPaginationType', 'bootstrap')
              ->setOptions('bFilter', false)
              ->setOptions('bAutoWidth', false)
              ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[1]]])
              ->render('datatable') !!}







    <script>
        window.onDatatableReady = actionListHandler;
    </script>
@stop
