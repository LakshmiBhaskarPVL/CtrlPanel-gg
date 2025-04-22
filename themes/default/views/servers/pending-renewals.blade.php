@extends('layouts.main')

@section('content')
    <!-- CONTENT HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="mb-2 row">
                <div class="col-sm-6">
                    <h1>{{__('Pending Server Renewals')}}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{__('Dashboard')}}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('servers.index') }}">{{__('Servers')}}</a></li>
                        <li class="breadcrumb-item active">{{__('Pending Renewals')}}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <!-- END CONTENT HEADER -->

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{__('Select Servers to Renew')}}</h3>
                            <div class="card-tools">
                                <span class="badge badge-info">
                                    {{__('Available Credits')}}: <span id="available-credits">{{ number_format($available_credits, 2) }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            @if(count($servers) > 0)
                                <form id="renewal-form" action="{{ route('servers.renew-selected') }}" method="POST">
                                    @csrf
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th width="5%">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="select-all">
                                                            <label class="custom-control-label" for="select-all"></label>
                                                        </div>
                                                    </th>
                                                    <th>{{__('Server Name')}}</th>
                                                    <th>{{__('Product')}}</th>
                                                    <th>{{__('Next Billing')}}</th>
                                                    <th>{{__('Price')}}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($servers as $server)
                                                    <tr>
                                                        <td>
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input server-checkbox" 
                                                                    id="server-{{ $server['id'] }}" 
                                                                    name="server_ids[]" 
                                                                    value="{{ $server['id'] }}"
                                                                    data-price="{{ $server['price'] }}">
                                                                <label class="custom-control-label" for="server-{{ $server['id'] }}"></label>
                                                            </div>
                                                        </td>
                                                        <td>{{ $server['name'] }}</td>
                                                        <td>{{ $server['product_name'] }}</td>
                                                        <td>{{ \Carbon\Carbon::parse($server['next_billing'])->format('Y-m-d H:i:s') }}</td>
                                                        <td>{{ number_format($server['price'], 2) }} {{__('credits')}}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <p>{{__('Total Selected')}}: <span id="total-selected">0</span></p>
                                        <p>{{__('Total Cost')}}: <span id="total-cost">0</span> {{__('credits')}}</p>
                                        <button type="submit" class="btn btn-primary" id="renew-button" disabled>
                                            {{__('Renew Selected Servers')}}
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="text-center">
                                    <p>{{__('No servers pending renewal')}}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- END CONTENT -->

    @push('scripts')
    <script>
        $(document).ready(function() {
            const availableCredits = parseFloat($('#available-credits').text().replace(/,/g, ''));
            
            function updateTotals() {
                let totalSelected = 0;
                let totalCost = 0;
                
                $('.server-checkbox:checked').each(function() {
                    totalSelected++;
                    totalCost += parseFloat($(this).data('price'));
                });
                
                $('#total-selected').text(totalSelected);
                $('#total-cost').text(totalCost.toFixed(2));
                
                // Enable/disable renew button based on selection and available credits
                $('#renew-button').prop('disabled', totalSelected === 0 || totalCost > availableCredits);
            }
            
            // Handle select all checkbox
            $('#select-all').change(function() {
                $('.server-checkbox').prop('checked', $(this).is(':checked'));
                updateTotals();
            });
            
            // Handle individual checkboxes
            $('.server-checkbox').change(function() {
                updateTotals();
                
                // Update select all checkbox
                const totalCheckboxes = $('.server-checkbox').length;
                const totalChecked = $('.server-checkbox:checked').length;
                $('#select-all').prop('checked', totalChecked === totalCheckboxes);
            });
            
            // Handle form submission
            $('#renewal-form').submit(function(e) {
                e.preventDefault();
                
                const totalCost = parseFloat($('#total-cost').text());
                if (totalCost > availableCredits) {
                    Swal.fire({
                        title: '{{__("Error")}}',
                        text: '{{__("Insufficient credits for selected servers")}}',
                        icon: 'error'
                    });
                    return;
                }
                
                Swal.fire({
                    title: '{{__("Confirm Renewal")}}',
                    text: '{{__("Are you sure you want to renew the selected servers?")}}',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '{{__("Yes, renew them!")}}',
                    cancelButtonText: '{{__("Cancel")}}'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = $(this);
                        $.ajax({
                            url: form.attr('action'),
                            type: 'POST',
                            data: form.serialize(),
                            success: function(response) {
                                Swal.fire({
                                    title: '{{__("Success!")}}',
                                    text: response.message,
                                    icon: 'success'
                                }).then(() => {
                                    window.location.reload();
                                });
                            },
                            error: function(xhr) {
                                Swal.fire({
                                    title: '{{__("Error")}}',
                                    text: xhr.responseJSON?.message || '{{__("An error occurred while processing your request")}}',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });
        });
    </script>
    @endpush
@endsection