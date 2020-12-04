@extends('layouts.app')

@section('left-nav')

            <ul class="navcolUL">
              <li><a href="{{ route('core_concepts') }}">Core Concepts</a></li>
              <li><a href="{{ route('available_content') }}">Available Content</a></li>
              <li><a href="{{ route('user_flows') }}">User Flows</a></li>
              <li><a href="{{ route('glossary') }}">Glossary</a></li>
              <li><a href="{{ route('api_reference') }}">API Reference</a></li>
			</ul>
@endsection		


