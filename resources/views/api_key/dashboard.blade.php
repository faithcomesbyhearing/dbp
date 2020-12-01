@extends('layouts.app-api-key')

@section('head')
<title>{{ $user->name }}'s Dashboard</title>
<script>
    function changeItemState(id, state) {
        showModal(true);
        console.log(id, state);
    }

    function addNote(id, note) {
        showModal(true);
        console.log(id, note);
    }

    function showEmailForm(id, email) {
        $("#email_error").css('display', 'none');
        $("#email_error").html('');
        $("#to_email").val(email);
        $("#subject").val("Your API Key request for Digital Bible Platform");
        $("#email_modal").data('id', id);
        $("#email_modal").css('display', 'flex');
    }

    $(document).ready(function() {
        var loading = false;

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
    });
</script>
<style>
    .dashboard_modal {
        display: none;
        background-color: #34343488;
        position: fixed;
        z-index: 2;
        width: 100%;
        top: 0px;
        bottom: 0px;
        overflow: hidden;
    }

    .dashboard_modal .field_error {
        display: none;
        color: red;
    }
</style>
@endsection

@section('content')
<div style="margin-top:100px">
    @if($user->roles->where('slug','admin')->first())
    <h2>Key Management</h2>
    <div>
        <form method="GET" action="{{ route('api_key.dashboard') }}">
            <label for="state_filter">Filter By State</label>
            <select name="state" id="state_filter" onchange="this.form.submit()">
                <option value=""></option>
                @foreach($options as $option)
                <option value="{{$option['value']}}" {{$option['selected'] ? 'selected':''  }}>{{$option['name']}}</option>
                @endforeach
            </select>
            <label for="search_filter">Search</label>
            <input type="text" name="search" id="search_filter" placeholder="Filter by name, email or key" value="{{ $search }}" />
        </form>
    </div>
    @if(!$key_requests->isEmpty())

    <table>
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
                <td><button onclick="showEmailForm({{ $key_request->id }},'{{$key_request->email}}')">{{ $key_request->email }}</button></td>
                <td>{{ $key_request->description }}</td>
                <td>{{ $key_request->questions }}</td>
                <td>@if($key_request->notes)
                    {{$key_request->notes}}
                    @else
                    <button onclick="addNote({{ $key_request->id }},'{{$key_request->notes}}')">Add note</button>
                    @endif
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
    <div>
        {{ $key_requests->appends(['state' => $state,'search' => $search])->links() }}
    </div>

    @else
    <p>Empty Results</p>
    @endif
    @else
    <p>You are not an admin</p>
    @endif
</div>

<div class="dashboard_modal" id="email_modal">
    <div class="card">
        <div>
            <h3>Send E-mail</h3><a class="close_modal" href="#">X</a>
        </div>
        <p class="field_error" id="email_error"></p>
        <div>
            <label for="to_email">To</label><input id="to_email" name="to_email" type="text" value="" required />
        </div>
        <div>
            <label for="subject">Subject</label><input id="subject" name="subject" type="text" value="" required />
        </div>
        <div>
            <textarea id="email_message" name="email_message" required>Hi! Your API Key...</textarea>
        </div>
        <input id="button_send_email" type="button" value="Send" />
    </div>
</div>



@endsection