@extends('layouts.app')

@section('left-nav')
	<ul class="navcolUL">
			  <li><a href="{{ route('legal_overview')}}">Overview</a></li>
              <li><a href="{{ route('legal_license')}}">License</a></li>              
              <li><a href="{{ route('legal_terms')}}">Terms & Conditions</a></li>    
              <li><a href="{{ route('privacy_policy')}}">Privacy Policy</a></li>   
    </ul>
@endsection	