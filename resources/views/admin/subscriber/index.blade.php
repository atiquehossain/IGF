@extends('admin.layouts.master')

@section('content')
  @php
    $canEmailSubscribers = app(\App\Http\Middleware\Permission::class)
      ->allows(auth('admin')->user(), 'subscriber.sendEmail');
    $canDeleteSubscribers = app(\App\Http\Middleware\Permission::class)
      ->allows(auth('admin')->user(), 'subscriber.destroy');
    $canExportSubscribers = app(\App\Http\Middleware\Permission::class)
      ->allows(auth('admin')->user(), 'subscriber.export');
    $hasSubscriberActions = $canEmailSubscribers || $canDeleteSubscribers;
  @endphp
  <div class="content pb-0">
    <h1 class="sr-only">Email subscribers</h1>
    <div class="row">
      <div class="col-lg-12 col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="row d-flex">
              <div class="col-md-3">
                <strong class="card-title">Email List</strong>
                @if(!$hasSubscriberActions)
                  <small class="d-block text-muted mt-1">Read-only email list. Your role can review subscribers{{ $canExportSubscribers ? ' and export the list' : '' }}, but cannot send email or remove records.</small>
                @endif
              </div>
              <div class="col-md-9">
                @if($canExportSubscribers)
                  <a href="{{ route('subscriber-excel-download.index') }}" class="btn igf-btn igf-btn-secondary igf-btn-compact float-right mb-2"
                    target="_blank" rel="noopener"><i class="fa fa-download" aria-hidden="true"></i> Export subscriber list</a>
                @endif
                <form action="{{ route('subscriber.filter') }}" method="post" class="form-inline float-right mr-2" role="search">@csrf
                  <label class="sr-only" for="subscriber-search">Search subscriber email</label>
                  <input id="subscriber-search" type="search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" required class="form-control form-control-sm mr-1" placeholder="Subscriber email">
                  <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
                </form>
                @if($search !== '')<form action="{{ route('subscriber.search.clear') }}" method="post" class="float-right mr-2">@csrf<button type="submit" class="btn igf-btn igf-btn-tertiary"><i class="fa fa-undo" aria-hidden="true"></i> Clear</button></form>@endif
              </div>
            </div>
          </div>
          <div class="table-stats ov-h">
            <table class="table" id="message_table">
              <thead>
                <tr>
                  <td width="45%"><strong>Email</strong></td>
                  <td width="45%"><strong>Date</strong></td>
                  @if($hasSubscriberActions)<th width="10%"><strong>Action</strong></th>@endif
                </tr>
              </thead>
              <tbody>
                @foreach ($subscribers as $message)
                  <tr id="{{ @$message->id }}">
                    <td>{{ @$message->email }}</td>
                    <td>{{ @$message->created_at }}</td>
                    @if($hasSubscriberActions)
                      <td>
                        @if($canEmailSubscribers)
                        <button type="button" class="btn igf-btn igf-btn-secondary igf-btn-compact send-email-btn"
                          data-email="{{ $message->email }}" title="Send email" aria-label="Send email to {{ $message->email }}">
                          <i class="fa fa-envelope" aria-hidden="true"></i> Email
                        </button>
                        @endif
                        @if($canDeleteSubscribers)
                          <button type="button" class="btn igf-btn igf-btn-danger igf-btn-compact trash"
                            data-id="{{ $message->id }}" data-url="{{ route('subscriber.destroy', $message->id) }}"
                            data-item-label="subscriber {{ $message->email }}"
                            aria-label="Remove {{ $message->email }} from subscribers" title="Remove subscriber">
                            <i class="fa fa-trash-o" aria-hidden="true"></i> Remove
                          </button>
                        @endif
                      </td>
                    @endif
                  </tr>
                @endforeach
              </tbody>
            </table>
            <div class="pagination justify-content-end">
              {{ $subscribers->links('vendor.pagination.bootstrap-4') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if($canEmailSubscribers)
    <div class="modal fade" id="sendEmailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <form id="sendEmailForm">
        @csrf
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title h5 mb-0" id="emailModalLabel">Send Email to Subscriber</h2>
            <button type="button" class="close btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="email" id="emailToSend">
            <div class="form-group">
              <label for="subject">Subject</label>
              <input type="text" name="subject" class="form-control" id="subject" required>
            </div>
            <div class="form-group ">
              <label for="message">Message</label>
              <textarea name="message" id="message" class="form-control" rows="4" required></textarea>
            </div>
            <div class="form-group">
              <label for="signature_image">Email Signature (Optional)</label>
              <input type="file" name="signature_image" class="form-control-file" id="signature_image">
              <small class="form-text text-muted">Upload an image to be included at the end of your email.</small>
              <div id="signature-preview" style="margin-top: 10px; max-width: 300px; display: none;">
                <img src="#" alt="Signature Preview" style="max-width: 100%; height: auto;">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn igf-btn igf-btn-primary"><i class="fa fa-paper-plane" aria-hidden="true"></i> Send email</button>
            <button type="button" class="btn igf-btn igf-btn-secondary" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Close</button>
          </div>
        </div>
      </form>
    </div>
    </div>
  @endif
@endsection

@section('custom-js')
  <script src="{{ asset('admin-assets/assets/js/jquery.form.min.js') }}"></script>

  <script>
    $(document).ready(function() {

      @if($canDeleteSubscribers)
        itemDelete({
          tableId: "message_table",
          method: "DELETE"
        });
      @endif

      @if($canEmailSubscribers)
      $('.send-email-btn').on('click', function() {
        $('#emailToSend').val($(this).data('email'));
        $('#sendEmailModal').modal('show');
      });

      const spinner = $('.spinner'); // Initialize the spinner variable
      spinner.hide(); // Hide the spinner initially

      $('#sendEmailForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        $.ajax({
          url: '{{ route('subscriber.sendEmail') }}',
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          beforeSend: function() {
            spinner.show(); // Show spinner before AJAX request
          },
          success: function(res) {
            alert(res.message);
            $('#sendEmailModal').modal('hide');
            $('#sendEmailForm')[0].reset();
            $('#signature-preview').hide();
            $('#signature-preview img').attr('src', '#');
          },
          error: function(xhr, status, error) {
            let errorMessage = 'Failed to send email.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
              errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
              errorMessage = xhr.responseText;
            }
            alert(errorMessage);
          },
          complete: function() {
            spinner.hide(); // Hide spinner after AJAX request completes (success or error)
          }
        });
      });

      $('#signature_image').on('change', function() {
        const file = this.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = (e) => {
            $('#signature-preview img').attr('src', e.target.result);
            $('#signature-preview').show();
          }
          reader.readAsDataURL(file);
        } else {
          $('#signature-preview').hide();
          $('#signature-preview img').attr('src', '#');
        }
      });
      @endif
    });
  </script>
@endsection
