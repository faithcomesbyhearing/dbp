@extends('layouts.app-api-key')

@section('head')
<title>{{ $user->name }}'s Dashboard</title>
@endsection

@section('content')

@if($user->projectMembers->where('role_id',2)->first())
<p>You are an admin</p>
@else
<p>You are not an admin</p>
@endif

@endsection