@extends('layouts.apiKey')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
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

        if (state == 1) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
                }
            });
            var formData = {
                email: email,
                key_request_id: id,
                name: name,
                key: key,
                description: description
            };
            $.ajax({
                type: "POST",
                url: "{{route('api_key.approve_api_key')}}",
                dataType: 'json',
                data: formData,
                error: function(xhr) {
                    console.log('error', xhr.responseText);
                }
            });
        }
    }

    $(document).ready(function() {
        var loading = false;
        var keys = <?php echo json_encode(
                        collect($key_requests->items())->mapWithKeys(function ($item) {
                            return [$item['id'] => $item];
                        })
                    ); ?>;
        $(".email_row").click(function(e) {
            var id = $(this).data('id');
            var email = keys[id].email;
            $("#email_error").css('display', 'none');
            $("#email_error").html('');
            $("#to_email").val(email);
            $("#subject").val("Your API Key request for Digital Bible Platform");
            $("#email_modal").data('id', id);
            $("#email_modal").css('display', 'flex');
        });

        $("#button_send_email").click(function(e) {
            e.preventDefault();
            var id = $("#email_modal").data('id');
            var email = $("#to_email").val();
            var subject = $("#subject").val();
            var message = $("#email_message").val();
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

        $(".note_row").click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var note = keys[parseInt(id)].notes;
            var info = $(this).data('info');

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
        });

        $("#button_save_note").click(function(e) {
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

        $(".close_modal").click(function(e) {
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
            <label for="state_filter">Filter By State</label>
            <select name="state" id="state_filter" onchange="this.form.submit()">
                @foreach($options as $option)
                <option value="{{$option['value']}}" {{$option['selected'] ? 'selected':''  }}>{{$option['name']}}</option>
                @endforeach
            </select>
            <label class="search-filter" for="search_filter">Search</label>
            <input type="text" name="search" id="search_filter" placeholder="Filter by name, email or key" value="{{ $search }}" />
        </form>
    </div>
    @if(!$key_requests->isEmpty())
    <table class="key-table">
        <thead>
            <tr>
                <th scope="col">Data Requested</th>
                <th scope="col">Name</th>
                <th scope="col">E-mail</th>
                <th scope="col">Purpose</th>
                <th scope="col">Comment/Question</th>
                <th scope="col">Notes</th>
                <th scope="col">Key</th>
                <th scope="col">State</th>
            </tr>
        </thead>
        <tbody>
            @foreach($key_requests as $key_request)
            <tr>
                <td>{{ $key_request->created_at }}</th>
                <td>{{ $key_request->name }}</td>
                <td><a href="#" class="email_row" data-id="{{ $key_request->id }}">{{ $key_request->email }}</a></td>
                <td>{{ $key_request->description }}</td>
                <td>{{ $key_request->questions }}</td>
                <td>
                    <div id="note-{{$key_request->id}}" data-id="{{$key_request->id}}" class="note_row">{{ $key_request->notes ?? 'Add a note' }}</div>
                </td>
                <td>{{ $key_request->temporary_key }}</td>
                <td>
                    <select name="key_state" onchange="changeItemState({{ $key_request->id }},this.value)">
                        @foreach($options as $option)
                        <option value="{{$option['value']}}" {{ $key_request->state === $option['value'] ? 'selected':''  }}>{{$option['name']}}</option>
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
        <div class="row input-request">
            <textarea class="input email-input comment" id="email_message" name="email_message" required>Hi! Your API Key...</textarea>
        </div>
        <input class="btn btn-success agreement-btn" id="button_send_email" type="button" value="Send" />
    </div>
</div>

<div class="dashboard_modal" id="note_modal">
    <div class="card">
        <div>
            <h3></h3><a class="close_modal" href="#">X</a>
        </div>
        <p class="field_error" id="note_error"></p>
        <p id="note_info"></p>
        <div>
            <textarea id="note_content" required></textarea>
        </div>
        <input id="button_save_note" type="button" value="Save" />
    </div>
</div>



@endsection