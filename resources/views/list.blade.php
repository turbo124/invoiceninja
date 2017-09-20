{!! Former::open(Utils::pluralizeEntityType($entityType) . '/bulk')
		->addClass('listForm_' . $entityType) !!}

<div style="display:none">
	{!! Former::text('action')->id('action_' . $entityType) !!}
    {!! Former::text('public_id')->id('public_id_' . $entityType) !!}
    {!! Former::text('datatable')->value('true') !!}
</div>

<div class="pull-left">
	@can('create', 'invoice')
		@if ($entityType == ENTITY_TASK)
			{!! Button::primary(trans('texts.invoice'))->withAttributes(['class'=>'invoice', 'onclick' =>'submitForm_'.$entityType.'("invoice")'])->appendIcon(Icon::create('check')) !!}
		@endif
		@if ($entityType == ENTITY_EXPENSE)
			{!! Button::primary(trans('texts.invoice'))->withAttributes(['class'=>'invoice', 'onclick' =>'submitForm_'.$entityType.'("invoice")'])->appendIcon(Icon::create('check')) !!}
		@endif
	@endcan

	{!! DropdownButton::normal(trans('texts.archive'))
			->withContents($datatable->bulkActions())
			->withAttributes(['class'=>'archive'])
			->split() !!}

	&nbsp;
	<span id="statusWrapper_{{ $entityType }}" style="display:none">
		<select class="form-control" style="width: 220px" id="statuses_{{ $entityType }}" multiple="true">
			@if (count(\App\Models\EntityModel::getStatusesFor($entityType)))
				<optgroup label="{{ trans('texts.entity_state') }}">
					@foreach (\App\Models\EntityModel::getStatesFor($entityType) as $key => $value)
						<option value="{{ $key }}">{{ $value }}</option>
					@endforeach
				</optgroup>
				<optgroup label="{{ trans('texts.status') }}">
					@foreach (\App\Models\EntityModel::getStatusesFor($entityType) as $key => $value)
						<option value="{{ $key }}">{{ $value }}</option>
					@endforeach
				</optgroup>
			@else
				@foreach (\App\Models\EntityModel::getStatesFor($entityType) as $key => $value)
					<option value="{{ $key }}">{{ $value }}</option>
				@endforeach
			@endif
		</select>
	</span>
</div>

<div id="top_right_buttons" class="pull-right">
	<input id="tableFilter_{{ $entityType }}" type="text" style="width:180px;margin-right:17px;background-color: white !important"
        class="form-control pull-left" placeholder="{{ trans('texts.filter') }}" value="{{ Input::get('filter') }}"/>

	@if ($entityType == ENTITY_INVOICE && auth()->user()->account->isModuleEnabled(ENTITY_RECURRING_INVOICE))
		{!! DropdownButton::normal(trans('texts.recurring'))
			->withAttributes(['class'=>'recurringDropdown'])
			->withContents([
			  ['label' => trans('texts.new_recurring_invoice'), 'url' => url('/recurring_invoices/create')],
			]
		  )->split() !!}
		<script type="text/javascript">
			$(function() {
				$('.recurringDropdown:not(.dropdown-toggle)').click(function() {
					window.location = '{{ url('/recurring_invoices') }}';
				});
			});
		</script>
    @elseif ($entityType == ENTITY_EXPENSE)
		{!! DropdownButton::normal(trans('texts.recurring'))
			->withAttributes(['class'=>'recurringDropdown'])
			->withContents([
			  ['label' => trans('texts.new_recurring_expense'), 'url' => url('/recurring_expenses/create')],
			]
		  )->split() !!}
	    {!! DropdownButton::normal(trans('texts.categories'))
			->withAttributes(['class'=>'categoriesDropdown'])
			->withContents([
			  ['label' => trans('texts.new_expense_category'), 'url' => url('/expense_categories/create')],
			]
		  )->split() !!}
	  	<script type="text/javascript">
		  	$(function() {
				$('.recurringDropdown:not(.dropdown-toggle)').click(function() {
		  			window.location = '{{ url('/recurring_expenses') }}';
		  		});
				$('.categoriesDropdown:not(.dropdown-toggle)').click(function() {
		  			window.location = '{{ url('/expense_categories') }}';
		  		});
			});
		</script>
	@elseif ($entityType == ENTITY_TASK)
		{!! DropdownButton::normal(trans('texts.projects'))
			->withAttributes(['class'=>'projectsDropdown'])
			->withContents([
			  ['label' => trans('texts.new_project'), 'url' => url('/projects/create')],
			]
		  )->split() !!}
	  	<script type="text/javascript">
		  	$(function() {
		  		$('.projectsDropdown:not(.dropdown-toggle)').click(function() {
		  			window.location = '{{ url('projects') }}';
		  		});
			});
		</script>
    @endif

	@if (Auth::user()->can('create', $entityType) && empty($vendorId))
    	{!! Button::primary(mtrans($entityType, "new_{$entityType}"))->asLinkTo(url(Utils::pluralizeEntityType($entityType) . '/create/' . (isset($clientId) ? $clientId : '')))->appendIcon(Icon::create('plus-sign')) !!}
	@endif

</div>


{!! Datatable::table()
	->addColumn(Utils::trans($datatable->columnFields(), $datatable->entityType))
	->setUrl(url('api/' . Utils::pluralizeEntityType($entityType) . '/' . (isset($clientId) ? $clientId : (isset($vendorId) ? $vendorId : ''))))
	->setCustomValues('entityType', Utils::pluralizeEntityType($entityType))
	->setCustomValues('clientId', isset($clientId) && $clientId)
	->setOptions('sPaginationType', 'bootstrap')
    ->setOptions('aaSorting', [[isset($clientId) ? ($datatable->sortCol-1) : $datatable->sortCol, 'desc']])
	->render('datatable') !!}

@if ($entityType == ENTITY_PAYMENT)
	@include('partials/refund_payment')
@endif

{!! Former::close() !!}

<style type="text/css">

	@foreach ($datatable->rightAlignIndices() as $index)
		.listForm_{{ $entityType }} table.dataTable td:nth-child({{ $index }}) {
			text-align: right;
		}
	@endforeach

	@foreach ($datatable->centerAlignIndices() as $index)
		.listForm_{{ $entityType }} table.dataTable td:nth-child({{ $index }}) {
			text-align: center;
		}
	@endforeach


</style>

<script type="text/javascript">

	function submitForm_{{ $entityType }}(action, id) {
		if (id) {
			$('#public_id_{{ $entityType }}').val(id);
		}

		if (action == 'delete' || action == 'emailInvoice') {
	        sweetConfirm(function() {
	            $('#action_{{ $entityType }}').val(action);
	    		$('form.listForm_{{ $entityType }}').submit();
	        });
		} else {
			$('#action_{{ $entityType }}').val(action);
			$('form.listForm_{{ $entityType }}').submit();
	    }
	}

	$(function() {

		// Handle datatable filtering
	    var tableFilter = '';
	    var searchTimeout = false;

	    function filterTable_{{ $entityType }}(val) {
	        if (val == tableFilter) {
	            return;
	        }
	        tableFilter = val;
			var oTable0 = $('.listForm_{{ $entityType }} .data-table').dataTable();
	        oTable0.fnFilter(val);
	    }

	    $('#tableFilter_{{ $entityType }}').on('keyup', function(){
	        if (searchTimeout) {
	            window.clearTimeout(searchTimeout);
	        }
	        searchTimeout = setTimeout(function() {
	            filterTable_{{ $entityType }}($('#tableFilter_{{ $entityType }}').val());
	        }, 500);
	    })

	    if ($('#tableFilter_{{ $entityType }}').val()) {
	        filterTable_{{ $entityType }}($('#tableFilter_{{ $entityType }}').val());
	    }

		$('.listForm_{{ $entityType }} .head0').click(function(event) {
			if (event.target.type !== 'checkbox') {
				$('.listForm_{{ $entityType }} .head0 input[type=checkbox]').click();
			}
		});

		// Enable/disable bulk action buttons
	    window.onDatatableReady_{{ Utils::pluralizeEntityType($entityType) }} = function() {
	        $(':checkbox').click(function() {
	            setBulkActionsEnabled_{{ $entityType }}();
	        });

	        $('.listForm_{{ $entityType }} tbody tr').unbind('click').click(function(event) {
	            if (event.target.type !== 'checkbox' && event.target.type !== 'button' && event.target.tagName.toLowerCase() !== 'a') {
	                $checkbox = $(this).closest('tr').find(':checkbox:not(:disabled)');
	                var checked = $checkbox.prop('checked');
	                $checkbox.prop('checked', !checked);
	                setBulkActionsEnabled_{{ $entityType }}();
	            }
	        });

	        actionListHandler();
	    }

	    $('.listForm_{{ $entityType }} .archive, .invoice').prop('disabled', true);
	    $('.listForm_{{ $entityType }} .archive:not(.dropdown-toggle)').click(function() {
	        submitForm_{{ $entityType }}('archive');
	    });

	    $('.listForm_{{ $entityType }} .selectAll').click(function() {
	        $(this).closest('table').find(':checkbox:not(:disabled)').prop('checked', this.checked);
	    });

	    function setBulkActionsEnabled_{{ $entityType }}() {
	        var buttonLabel = "{{ trans('texts.archive') }}";
	        var count = $('.listForm_{{ $entityType }} tbody :checkbox:checked').length;
	        $('.listForm_{{ $entityType }} button.archive, .listForm_{{ $entityType }} button.invoice').prop('disabled', !count);
	        if (count) {
	            buttonLabel += ' (' + count + ')';
	        }
	        $('.listForm_{{ $entityType }} button.archive').not('.dropdown-toggle').text(buttonLabel);
	    }


		// Setup state/status filter
		$('#statuses_{{ $entityType }}').select2({
			placeholder: "{{ trans('texts.status') }}",
			//allowClear: true,
			templateSelection: function(data, container) {
				if (data.id == 'archived') {
					$(container).css('color', '#fff');
					$(container).css('background-color', '#f0ad4e');
					$(container).css('border-color', '#eea236');
				} else if (data.id == 'deleted') {
					$(container).css('color', '#fff');
					$(container).css('background-color', '#d9534f');
					$(container).css('border-color', '#d43f3a');
				}
				return data.text;
			}
		}).val('{{ session('entity_state_filter:' . $entityType, STATUS_ACTIVE) . ',' . session('entity_status_filter:' . $entityType) }}'.split(','))
			  .trigger('change')
		  .on('change', function() {
			var filter = $('#statuses_{{ $entityType }}').val();
			if (filter) {
				filter = filter.join(',');
			} else {
				filter = '';
			}
			var url = '{{ URL::to('set_entity_filter/' . $entityType) }}' + '/' + filter;
	        $.get(url, function(data) {
	            refreshDatatable_{{ Utils::pluralizeEntityType($entityType) }}();
	        })
		}).maximizeSelect2Height();

		$('#statusWrapper_{{ $entityType }}').show();


		@for ($i = 1; $i <= 10; $i++)
			Mousetrap.bind('g {{ $i }}', function(e) {
				var link = $('.data-table').find('tr:nth-child({{ $i }})').find('a').attr('href');
				if (link) {
					location.href = link;
				}
			});
		@endfor
	});

</script>
