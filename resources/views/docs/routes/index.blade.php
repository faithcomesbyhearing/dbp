@extends('layouts.docs')
     
@section('docs-content')
          <!-- Right Content Area -->
          <div class="col-9 content-column">
            <h2>Developer Documentation</h2>
            <p class="txt-intro">Everything you need to know when working with the Digital Bible Platform API</p>

            <div class="row">
              <div class="col-sm-4">
                <a href="{{ route('core_concepts') }}" class="card">
                  <div class="card-body">
                    <div class="card-title txt-intro--regular">Core Concepts</div>
                    <p class="card-text txt-md">Provides some of the fundamental concepts for understanding how to interface with the DBP.</p>
                  </div>
                </a>
              </div>

              <div class="col-sm-4">
                <a href="{{ route('available_content') }}" class="card">
                  <div class="card-body">
                    <div class="card-title txt-intro--regular">Available Content</div>
                    <p class="card-text txt-md">Provides information on what content is available</p>
                  </div>
                </a>
            </div>

              <div class="col-sm-4">
                <a href="{{ route('user_flows') }}" class="card">
                  <div class="card-body">
                    <div class="card-title txt-intro--regular">User Flows</div>
                    <p class="card-text txt-md">Provides examples of user experience, from selecting a language to listening to or reading the Bible.</p>
                  </div>
                </a>
              </div>
              
            </div>

            <div class="row">

              <div class="col-sm-4">
                <a href="{{ route('glossary') }}" class="card">
                  <div class="card-body">
                    <div class="card-title txt-intro--regular">Glossary</div>
                    <p class="card-text txt-md">Provides definitions for commonly-used terms</p>
                  </div>
                </a>
              </div>

              <div class="col-sm-4">
                <a href="{{ route('api_reference') }}" class="card">
                  <div class="card-body">
                    <div class="card-title txt-intro--regular">API Reference</div>
                    <p class="card-text txt-md">Provides specific information about API request and response structure</p>
                  </div>
                </a>
              </div>



              
            </div>

            <div class="row">
              <div class="col-8"><p class="txt-subtitle">Before you begin building with the DBP you’ll need to ensure you have an API Key.</p></div>
              <div class="col-4"><a href="#" class="btn btn-lg">Get Your API Key</a></div>
            </div>


          </div> <!-- end div col-9 content-column -->

@endsection