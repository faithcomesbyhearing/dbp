@extends('layouts.apiKey')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
    function sendAjaxRequest(url, formData) {
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
        }
      });
      $.ajax({
          type: "POST",
          url,
          dataType: 'json',
          data: formData,
          success: function() {
            window.location.reload(); 
          },
          error: function(xhr) {
            console.log('error', xhr.responseText);
          }
      });
    }

    function changeItemState(id, state) {
      var keys = <?php echo json_encode(
        collect($key_requests->items())->mapWithKeys(function ($item) {
            return [$item['id'] => $item];
        })
      ); ?>;
      var email = keys[id].email;
      var name = keys[id].name;
      var key = keys[id].temporary_key;
      var description = keys[id].description;
      var formData = {
        email: email,
        key_request_id: id,
        name: name,
        key: key,
        description: description,
        state
      };

      switch(state) {
        case '1':
          sendAjaxRequest("{{route('api_key.change_api_key_state')}}", formData);
          break;
        case '2':
          sendAjaxRequest("{{route('api_key.approve_api_key')}}", formData);
          break;
        case '3':
          sendAjaxRequest("{{route('api_key.delete_api_key')}}", formData);
          break;
        default:
          break;
      }
    }

    $(document).ready(function() {
        var loading = false;
        var keys = <?php echo json_encode(
          collect($key_requests->items())->mapWithKeys(function ($item) {
              return [$item['id'] => $item];
          })
        ); ?>;

        $.fn.displayEmail = function (e, id) {
          var email = keys[id].email;
          $("#email_error").css('display', 'none');
          $("#email_error").html('');
          $("#to_email").val(email);
          $("#subject").val("DBP4 API Key approved");
          $("#email_message").html('');
          $("#email_message").html(
            "<p>Hello,</p></br>" + 
            "<p>Your recent request for a Digital Bible Platform version 4 (DBP4) API Key has been approved.</p>"+
            "<p>Your API Key is listed below. Please do not share it with anyone.</p>" + 
            "<p>With your access to DBP4 (also known as Bible Brain),  you get access to more content (for instance Gospel Films, and more verse timings)," + 
            "and in more formats (for instance smaller Opus, and HLS) than in previous versions.<p></br>" +
            "<p>If you have questions, please email support@digitalbibleplatform.com </p>" +
            "To learn more, go to https://biblebrain.com</p></br>" +
            `<p>Your API Key: ${keys[id].temporary_key}</p>`+
            "<p>Please do not share your API key.</p> <br>--</br>" + 
            "<p>Thank you,</p><br>" +
            '<a href="https://www.faithcomesbyhearing.com/bible-brain"> Digital Bible Platform</a> Team' +
            ' | <a href="https://www.faithcomesbyhearing.com/">Faith Comes By Hearing </a>' +
            "<p><strong>God's Word never changes...the way we interact with it does.</strong><p>"
          );
        
          $("#email_modal").data('id', id);
          $("#email_modal").css('display', 'flex');
        };

        $.fn.displayNote = function (e, id, info) {
          e.preventDefault();
          var note = keys[parseInt(id)].notes;
          $("#note_error, #note_info").css('display', 'none');
          $("#note_error, #note_info").html('');
          $("#button_save_note").data('id', id);

          if (note) {
              $("#note_modal h3").html('Revise a note');
              $("#note_content").attr('disabled', true);
              $("#note_content").val(note);
              $("#button_save_note").val('OK');
              $("#button_save_note").data('isNew', false);
          } else {
              if (info) {
                  $("#note_info").show();
                  $("#note_info").html(info);
              }
              $("#note_modal h3").html('Add a note');
              $("#note_content").val('{{date("m/d/Y", time())}}\n');
              $("#note_content").attr('disabled', false);
              $("#button_save_note").val('Save');
              $("#button_save_note").data('isNew', true);
          }
          $("#note_modal").css('display', 'flex');
        };

        $(".note_row").off().on('click', function(e) {
          var keyId = $(this).data('id');
          var info = $(this).data('info');
          $(".note_row").displayNote(e, keyId, info);
        });

        $(".email_row").off().on('click', function(e) {
          var keyId = keyId || $(this).data('id');
          $(".note_row").displayEmail(e, keyId);
        });

        $(".request_detail").off().on('click', function(e) {
            var key = $(this).data(key).key;
            var state = $(this).data(state).state;
            var keydate = $(this).data(keydate).keydate;
            var options = $(this).data(options).options;
            var noteComponent = 
              `<div>
                  <a href="#" id="note-${key.id}" data-id="${key.id}" class="note_row">
                      ${key.notes ?? 'Add a note'}
                  </a>
              </div>`;
            var emailComponent = 
              `<div>
                <span>${key.email}</span>
                <a href="#" class="email_row" data-id="${key.id}">(Send Email)</a>
              </div>`;
            var stateOptions = [];
            options.forEach(option => {
              if (option.value !== 0) {
                stateOptions.push(`<option value="${option.value}" ${key.state} ${key.state === option.value ? 'selected' : ''}>${option.name}</option>`);
              }
            });
            var stateComponent = 
              `<select name="key_state" onchange="changeItemState(${key.id}, this.value)">
                  ${stateOptions.join('')}
                </select>`;

            $("#detail_modal").data('key', key);
            $("#detail_modal").css('display', 'flex');
            $('#detail_name').text(`${key.name}`);
            $('#detail_email').html(emailComponent);
            $('#detail_email').off().on('click', function(e){
              $("#detail_modal").hide();
              $('#detail_email').displayEmail(e, key.id);
            });
            $('#detail_application_name').text(`${key.application_name}`);
            $('#detail_application_url').text(`${key.application_url}`);
            $('#detail_description').text(`${key.description}`);
            $('#detail_questions').text(`${key.questions}`);
            $('#detail_key').text(`${key.temporary_key}`);
            $('#detail_state').html(stateComponent);
            $('#detail_notes').html(noteComponent);
            $('#detail_notes').off().on('click', function(e){
              $("#detail_modal").hide();
              $('#detail_notes').displayNote(e, key.id);
            });
            $('#detail_date').text(`${keydate}`);
        });

        $("#button_send_email").off().on('click', function(e) {
            e.preventDefault();
            var id = $("#email_modal").data('id');
            var email = $("#to_email").val();
            var subject = $("#subject").val();
            var message = $("#email_message").html();

            $("#email_error").html('');
            if (!id || !email || !subject || !message) {
                $("#email_error").html('Please complete all the fields');
                $("#email_error").show();
            } else {
                loading = true;
                $("#email_modal input, #email_modal textarea").attr('disabled', true);
                $("#button_send_email").val('Sending...');
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
                    }
                });
                var formData = {
                    email: email,
                    id: id,
                    subject: subject,
                    message: message
                };
                $.ajax({
                    type: "POST",
                    url: "{{route('api_key.send_email')}}",
                    data: formData,
                    dataType: 'json',
                    success: function(data) {
                        $("#email_modal").hide();
                        resetEmailFields();
                        window.location.reload(); 
                    },
                    error: function(xhr) {
                        resetEmailFields();
                        var error = JSON.parse(xhr.responseText);
                        $("#email_error").html(error.error.message ? error.error.message : 'An error happened, please try again later');
                        $("#email_error").show();
                    }
                });
            }
        });

        $("#button_save_note").off().on('click', function(e) {
            e.preventDefault();
            var id = $("#button_save_note").data('id');
            var isNew = $("#button_save_note").data('isNew');
            if (!isNew) {
                $("#note_modal").hide();
                return;
            }

            var note = $("#note_content").val();
            $("#note_error").html('');
            if (!id || !note) {
                $("#note_error").html('Please add a note');
                $("#note_error").show();
            } else {
                loading = true;
                $("#note_modal input, #note_modal textarea").attr('disabled', true);
                $("#button_save_note").val('Saving...');
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
                    }
                });
                var formData = {
                    id: id,
                    note: note,
                };
                $.ajax({
                    type: "POST",
                    url: "{{route('api_key.save_note')}}",
                    data: formData,
                    dataType: 'json',
                    success: function(data) {
                        keys[data.id] = data;
                        $("#note-" + data.id).html(data.notes);
                        resetNoteFields();
                        $("#note_modal").hide();
                        window.location.reload(); 
                    },
                    error: function(xhr) {
                        resetNoteFields();
                        var error = JSON.parse(xhr.responseText);
                        $("#note_error").html(error.error.message ? error.error.message : 'An error happened, please try again later');
                        $("#note_error").show();
                    }
                });
            }
        });

        $(".close_modal").off().on('click', function(e) {
            e.preventDefault();
            if (!loading) {
                $(".dashboard_modal").hide();
            }
        });

        function resetEmailFields() {
            loading = false;
            $("#email_modal input, #email_modal textarea").attr('disabled', false);
            $("#button_send_email").val('Send');
        }

        function resetNoteFields() {
            loading = false;
            $("#note_modal input, #note_modal textarea").attr('disabled', false);
            $("#button_save_note").val('Save');
        }
    });
</script>
<link rel="stylesheet" href="{{ mix('css/app_api_key.css') }}" />
@endsection

@section('content')
@if($user->roles->where('slug','admin')->first())
<h2 class="dashboard-title">Key Management</h2>
<div class="dashboard-card">
    <div class="key-filter">
        <form method="GET" action="{{ route('api_key.dashboard') }}">
            <div class="row">
                <label for="state_filter">Filter By State</label>
                <select name="state" id="state_filter" onchange="this.form.submit()">
                    @foreach($options as $option)
                    <option value="{{$option['value']}}" {{$option['selected'] ? 'selected':''  }}>{{$option['name']}}</option>
                    @endforeach
                </select>
                <label class="search-filter" for="search_filter">Search</label>
                <input type="text" name="search" id="search_filter" placeholder="Filter by name, email or key" value="{{ $search }}" />
            </div>
            
        </form>
    </div>
    @if(!$key_requests->isEmpty())
    <table class="key-table">
        <thead class="key-table-head">
            <tr>
                <th> </th>
                <th scope="col">Date</th>
                <th scope="col">Name</th>
                <th scope="col">E-mail</th>
                <th scope="col">Purpose</th>
                <th scope="col">Comment/Question</th>
                <th scope="col">Notes</th>
                <th scope="col">State</th>
            </tr>
        </thead>
        <tbody class="key-table-body">
            @foreach($key_requests as $key_request)
            <tr >
                <td><a href="#" class="request_detail"  data-key="{{ $key_request }}" data-options="{{ json_encode($options) }}" data-state="{{ $state_names[$key_request->state] }}" data-keydate="{{ date_format($key_request['created_at'],'d/m/Y H:i:s') }}">details</a></td>
                <td>{{ date_format($key_request->created_at,"d/m/Y") }}</th>
                <td><div class="table-content">{{ $key_request->name }}</div></td>
                <td>
                    <div class="table-content">
                        <a href="#" class="email_row" data-id="{{ $key_request->id }}">
                            {{ $key_request->email }}
                        </a>
                    </div>
                </td>
                <td><div class="table-content">{{ $key_request->description }}</div></td>
                <td><div class="table-content">{{ $key_request->questions }}</div></td>
                <td>
                    <div class="table-content">
                        <a href="#" id="note-{{$key_request->id}}" data-id="{{$key_request->id}}" class="note_row">
                            {{ $key_request->notes ?? 'Add a note' }}
                        </a>
                    </div>
                </td>
                <td>
                    <select name="key_state" onchange="changeItemState({{ $key_request->id }},this.value)">
                        @foreach($options as $option)
                        @if ($option['value'] !== 0)
                        <option value="{{$option['value']}}" {{ $key_request->state === $option['value'] ? 'selected':''  }}>{{$option['name']}}</option>
                        @endif
                        @endforeach
                    </select>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p>Empty Results</p>
    @endif
</div>
@else
<p>You are not an admin</p>
@endif

<div class="pagination">
    {{$key_requests->appends(['state' => $state,'search' => $search])->links()}}
</div>

<div class="dashboard_modal" id="detail_modal">
    <div class="card detail-card">
        <a class="close_modal close" href="#"></a>
        <p class="card-header">Api Key details</p>
        
        <table class="key-table">
            <tr>
              <th scope="col">Name</th>
              <td id="detail_name"></td>
            </tr>
            <tr>
              <th scope="col">E-mail</th>
              <td id="detail_email"></td>
            </tr>
            <tr>
                <th scope="col">Description</th>
                <td id="detail_description"></td>
            </tr>
            <tr>
                <th scope="col">Application Name</th>
                <td id="detail_application_name"></td>
            </tr>
            <tr>
                <th scope="col">Application URL</th>
                <td id="detail_application_url"></td>
            </tr>
            <tr>
                <th scope="col">Question</th>
                <td id="detail_questions"></td>
            </tr>
            <tr>
                <th scope="col">Key</th>
                <td id="detail_key"></td>
            </tr>
            <tr>
                <th scope="col">Notes</th>
                <td id="detail_notes"></td>
            </tr>
            <tr>
                <th scope="col">State</th>
                <td id="detail_state"></td>
            </tr>
            <tr>
                <th scope="col">Request date</th>
                <td id="detail_date"></td>
            </tr>
        </table>
    </div>
</div>

<div class="dashboard_modal" id="email_modal">
    <div class="card email-card">
        <a class="close_modal close" href="#"></a>
        <p class="card-header email-title">Send E-mail</p>
        <p class="field_error" id="email_error"></p>
        <div class="row input-request">
            <label class="email-label" for="to_email">To</label>
            <input class="input email-input" id="to_email" name="to_email" type="text" value="" required />
        </div>
        <div class="row input-request">
            <label class="email-label" for="subject">Subject</label>
            <input class="input email-input" id="subject" name="subject" type="text" value="" required />
        </div>
        <div class="input email-input comment editable" contenteditable="true" id="email_message" name="email_message" required></div>
        <input class="btn btn-success agreement-btn" id="button_send_email" type="button" value="Send" />
    </div>
</div>

<div class="dashboard_modal" id="note_modal">
    <div class="card note-card">
        <a class="close_modal close" href="#"></a>
        <p class="card-header email-title">Create note</p>
        <p class="field_error" id="note_error"></p>
        <p id="note_info"></p>
        <div class="row input-request">
            <textarea class="input email-input comment" id="note_content" required></textarea>
        </div>
        <input class="btn btn-success agreement-btn" id="button_save_note" type="button" value="Save" />
    </div>
</div>



@endsection