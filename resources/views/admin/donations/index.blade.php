@extends('admin.layouts.master')

@section('content')
  <div class="content pb-0">
    <h1 class="sr-only">Donation records</h1>

    <div class="row mb-3">
      <div class="col-md-6 mb-2">
        <div class="card h-100 border-left-success"><div class="card-body">
          <div class="text-muted small text-uppercase">Successful gifts in this view</div>
          <div class="h3 mb-0">{{ number_format($successfulCount) }}</div>
        </div></div>
      </div>
      <div class="col-md-6 mb-2">
        <div class="card h-100 border-left-success"><div class="card-body">
          <div class="text-muted small text-uppercase">{{ $projectUuidFilter ? 'Amount attributed to selected project' : 'Successful amount in this view' }}</div>
          <div class="h3 mb-0">BDT {{ number_format((float) $successfulTotal, 2) }}</div>
          @if($projectUuidFilter)<div class="small text-muted">Direct gifts plus allocated portions only.</div>@endif
        </div></div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><strong>Find donation records</strong></div>
      <div class="card-body">
        <form action="{{ route('donations.search') }}" method="post" class="form-row align-items-end mb-3" role="search">
          @csrf
          <div class="col-lg-6 form-group mb-0">
            <label for="donation-search">Private search</label>
            <input id="donation-search" type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" class="form-control" placeholder="Transaction, donor, cause or project" required>
            <small class="form-text text-muted">The search value stays out of the address bar and expires after 10 minutes.</small>
          </div>
          <div class="col-auto"><button type="submit" class="btn btn-info"><i class="fa fa-search" aria-hidden="true"></i> Search</button></div>
        </form>
        @if($search !== '')
          <form action="{{ route('donations.search.clear') }}" method="post" class="mb-3">@csrf<button type="submit" class="btn btn-light btn-sm"><i class="fa fa-times" aria-hidden="true"></i> Clear private search</button></form>
        @endif
        <form action="{{ route('donations.index') }}" method="get">
          <div class="row">
            <div class="col-lg-2 col-md-6 form-group">
              <label for="donation-status">Payment status</label>
              <select id="donation-status" name="status" class="form-control">
                <option value="">All statuses</option>
                @foreach(['Pending', 'Success', 'Review', 'Failed', 'Cancelled'] as $status)
                  <option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
              <label for="destination-type">Destination type</label>
              <select id="destination-type" name="destination_type" class="form-control">
                <option value="">All destinations</option>
                @foreach($destinationOptions as $value => $label)
                  <option value="{{ $value }}" @selected($destinationTypeFilter === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
              <label for="cause-filter">Cause</label>
              <select id="cause-filter" name="cause_uuid" class="form-control">
                <option value="">All causes</option>
                @foreach($causeFilters as $cause)
                  <option value="{{ $cause->cause_uuid_snapshot }}" @selected($causeUuidFilter === (string) $cause->cause_uuid_snapshot)>{{ $cause->cause_name_snapshot ?: 'Historical cause' }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
              <label for="project-filter">Project attribution</label>
              <select id="project-filter" name="project_uuid" class="form-control">
                <option value="">All projects</option>
                @foreach($projectFilters as $project)
                  <option value="{{ $project['uuid'] }}" @selected($projectUuidFilter === (string) $project['uuid'])>{{ $project['name'] }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-info"><i class="fa fa-search" aria-hidden="true"></i> Apply filters</button>
          <a href="{{ route('donations.index') }}" class="btn igf-btn igf-btn-tertiary"><i class="fa fa-undo" aria-hidden="true"></i> Clear</a>
        </form>
      </div>
    </div>

    @if($projectAttribution->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header"><strong>Successful project attribution summary</strong></div>
        <div class="card-body p-0"><div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Project</th><th>Direct gifts</th><th>Later allocations</th><th>Total attributed</th><th>Gift records</th></tr></thead>
            <tbody>
              @foreach($projectAttribution as $project)
                <tr>
                  <td>{{ $project['name'] }}</td>
                  <td>BDT {{ number_format((float) $project['direct_amount'], 2) }}</td>
                  <td>BDT {{ number_format((float) $project['allocated_amount'], 2) }}</td>
                  <td><strong>BDT {{ number_format((float) $project['total_amount'], 2) }}</strong></td>
                  <td>{{ number_format($project['donation_count']) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div></div>
      </div>
    @endif

    <div class="card">
      <div class="card-header"><strong>Donation Records</strong></div>
      <div class="table-responsive">
        <table class="table" id="donation_table">
          <thead><tr><th>Transaction and donor</th><th>Gift</th><th>Donor designation</th><th>Payment / Gateway state</th><th style="min-width:300px">Project allocation</th><th style="min-width:230px">Review</th></tr></thead>
          <tbody>
            @forelse($donations as $donation)
              @php
                $paymentStatus = (string) ($donation->payment_status ?: 'Pending');
                $initializationState = strtoupper((string) ($donation->gatewayTransaction?->initialization_status ?: 'UNKNOWN'));
                $causeName = $donation->cause_name_snapshot ?: $donation->donationType?->name ?: 'Unspecified legacy donation';
                $destinationName = $donation->destination_name_snapshot ?: 'Unspecified destination';
                $requestedMethod = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card (Visa / Amex)'][$donation->requested_payment_method] ?? '—';
              @endphp
              <tr>
                <td><strong>{{ $donation->transaction_id }}</strong><br>{{ $donation->donor_name }}<br><span class="small text-muted">{{ $donation->email }} · {{ $donation->phone }}</span></td>
                <td>
                  <strong>BDT {{ number_format((float) $donation->amount, 2) }}</strong><br>
                  <span class="badge badge-{{ strtolower($paymentStatus) === 'success' ? 'success' : (strtolower($paymentStatus) === 'review' ? 'warning' : 'secondary') }}">{{ $paymentStatus }}</span>
                </td>
                <td>
                  <strong>{{ $causeName }}</strong><br>
                  <span class="small">{{ $destinationOptions[$donation->destination_type_snapshot] ?? 'Legacy destination' }}: {{ $destinationName }}</span>
                  @if($donation->allocation_unresolved_legacy)<div class="small text-danger mt-1"><strong>Unresolved legacy gift:</strong> donor intent is unknown. Do not allocate without a future audited reconciliation.</div>@endif
                  @if($donation->project_uuid_snapshot)<div class="small text-success mt-1"><strong>Project chosen by donor:</strong> {{ $donation->project_name_snapshot ?: 'Historical project' }}</div>@endif
                </td>
                <td>
                  Requested: {{ $requestedMethod }}<br>
                  Verified: {{ $donation->gatewayTransaction?->verified_payment_method_label ?? '—' }}<br>
                  <span class="small">Gateway: {{ str_replace('_', ' ', ucfirst(strtolower($initializationState))) }}</span>
                  @if($initializationState === 'UNCERTAIN')
                    <div class="text-danger small">
                      <strong>Reconciliation required</strong>
                      @if($donation->gatewayTransaction?->initialization_error)<br>{{ $donation->gatewayTransaction->initialization_error }}@endif
                    </div>
                  @endif
                </td>
                <td>
                  @if($donation->allocation_direct)
                    <div class="text-success"><strong>Fully attributed by the donor</strong></div>
                    <div class="small">BDT {{ number_format((float) $donation->allocated_amount, 2) }} to {{ $donation->project_name_snapshot ?: $destinationName }}</div>
                  @else
                    <div class="small mb-2">Allocated: <strong>BDT {{ number_format((float) $donation->allocated_amount, 2) }}</strong><br>Remaining: <strong>BDT {{ number_format((float) $donation->allocation_remaining, 2) }}</strong></div>
                    @if($donation->allocations->isNotEmpty())
                      <details class="mb-2">
                        <summary>{{ $donation->allocations->count() }} allocation {{ Str::plural('entry', $donation->allocations->count()) }}</summary>
                        <ul class="small pl-3 mt-2">
                          @foreach($donation->allocations as $allocation)
                            <li class="mb-1"><strong>{{ $allocation->page_name_snapshot }}</strong> — BDT {{ number_format((float) $allocation->amount, 2) }}<br>{{ $allocation->note }}<br><span class="text-muted">{{ $allocation->created_at?->format('Y-m-d H:i') }} by {{ $allocation->allocated_by_name_snapshot ?: $allocation->allocator?->name ?: 'Historical administrator' }}</span></li>
                          @endforeach
                        </ul>
                      </details>
                    @endif

                    @if($donation->allocation_unresolved_legacy)
                      <span class="small text-danger"><strong>Allocation blocked.</strong> This historical record has no verified cause snapshot; it is not treated as unrestricted money.</span>
                    @elseif(strtolower($paymentStatus) !== 'success')
                      <span class="small text-muted">Allocation becomes available after payment succeeds.</span>
                    @elseif((float) $donation->allocation_remaining <= 0)
                      <span class="small text-success">This gift is fully allocated.</span>
                    @elseif(!$canAllocate)
                      <span class="small text-muted">View only. An administrator with donation-allocation permission can record attribution.</span>
                    @elseif(empty($donation->allocation_options))
                      <span class="small text-warning">No eligible published project is available within this gift’s destination. Nothing was changed.</span>
                    @else
                      <form method="post" action="{{ route('donations.allocate', $donation) }}" class="border rounded p-2 js-allocation-form" data-designation="{{ $causeName }} — {{ $destinationName }}" data-remaining="{{ $donation->allocation_remaining }}">
                        @csrf
                        <input type="hidden" name="request_token" value="{{ $donation->allocation_request_token }}">
                        <div class="alert alert-warning small py-2 px-2"><strong>Permanent audit entry.</strong> Review the donor designation, project, amount, and remaining balance. Recorded allocations cannot be edited or deleted.</div>
                        <div class="form-group mb-2">
                          <label for="allocation-project-{{ $donation->id }}" class="small"><strong>Eligible project</strong></label>
                          <select id="allocation-project-{{ $donation->id }}" name="page_uuid" class="form-control form-control-sm" required>
                            <option value="">Choose a project</option>
                            @foreach($donation->allocation_options as $project)<option value="{{ $project['uuid'] }}">{{ $project['name'] }}{{ $project['is_zakat_eligible'] ? ' · Zakat eligible' : '' }}</option>@endforeach
                          </select>
                        </div>
                        <div class="form-group mb-2">
                          <label for="allocation-amount-{{ $donation->id }}" class="small"><strong>Amount (BDT)</strong></label>
                          <input id="allocation-amount-{{ $donation->id }}" type="number" name="amount" min="0.01" max="{{ $donation->allocation_remaining }}" step="0.01" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-group mb-2">
                          <label for="allocation-note-{{ $donation->id }}" class="small"><strong>Audit note</strong></label>
                          <textarea id="allocation-note-{{ $donation->id }}" name="note" rows="2" minlength="10" maxlength="1000" class="form-control form-control-sm" required placeholder="Why and how this project was selected"></textarea>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                          <input id="allocation-confirm-{{ $donation->id }}" class="custom-control-input" type="checkbox" name="confirm_allocation" value="1" required>
                          <label class="custom-control-label small" for="allocation-confirm-{{ $donation->id }}">I reviewed the donor designation and understand this allocation is append-only.</label>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm">Review and record allocation</button>
                      </form>
                    @endif
                  @endif
                </td>
                <td>
                  @if(strtolower($paymentStatus) === 'review')
                    <div class="small mb-2"><strong>Reason:</strong> {{ str_replace('_', ' ', $donation->review_reason ?: 'manual review required') }}</div>
                    @if($canResolveReview)
                      <form method="post" action="{{ route('donations.review.resolve', $donation) }}">
                        @csrf @method('PUT')
                        <label class="sr-only" for="resolution-note-{{ $donation->id }}">Resolution note</label>
                        <textarea id="resolution-note-{{ $donation->id }}" name="resolution_note" class="form-control form-control-sm mb-2" rows="2" minlength="10" maxlength="1000" required placeholder="Explain the evidence checked before approving"></textarea>
                        <button type="submit" class="btn btn-warning btn-sm">Resolve to success</button>
                      </form>
                    @else<span class="text-muted small">A payment reviewer must resolve this item.</span>@endif
                  @elseif($donation->review_resolved_at)
                    <span class="small">Resolved {{ $donation->review_resolved_at->format('Y-m-d H:i') }}</span>
                  @else
                    <span aria-hidden="true">—</span><span class="sr-only">No review action required</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center">No records found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer"><div class="pagination justify-content-end mb-0">{{ $donations->withQueryString()->links('vendor.pagination.bootstrap-4') }}</div></div>
    </div>
  </div>
@endsection

@section('custom-js')
<script>
document.querySelectorAll('.js-allocation-form').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    var project = form.querySelector('[name="page_uuid"] option:checked');
    var amount = form.querySelector('[name="amount"]').value;
    var summary = 'Final review\n\n'
      + 'Donor designation: ' + form.dataset.designation + '\n'
      + 'Project: ' + (project ? project.textContent.trim() : 'Not selected') + '\n'
      + 'Amount: BDT ' + amount + '\n'
      + 'Remaining before this entry: BDT ' + form.dataset.remaining + '\n\n'
      + 'Record this permanent allocation?';
    if (!window.confirm(summary)) {
      event.preventDefault();
    }
  });
});
</script>
@endsection
