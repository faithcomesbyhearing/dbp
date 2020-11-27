@extends('layouts.app')

@section('head')
    <style>
        small {
            display: block;
        }

        .docs {
            padding:20px;
        }
        .supported {
            background-color:darkgreen;
        }
    </style>
@endsection

@section('content')
<div id="content">
	<div class="wrap">
		<div id="developer">
            <div id="wrap">
                <div class="subnav">
        		     <li class="page_item page-item-1888"><a href="https://www.digitalbibleplatform.com/docs/core-concepts/">Core Concepts (original)</a></li>
                     <li class="page_item"><a href="{{ route('core_concepts')}}">Core Concepts</a></li>
                     <li class="page_item"><a href="{{ route('available_content')}}">Available Content</a></li>              
                     <li class="page_item"><a href="{{ route('api_reference')}}">API Reference</a></li>    
                     <li class="page_item"><a href="{{ route('user_flows')}}">User Flows</a></li>      
                </div>
                <div class="dev-docs">
                    @yield('docs-content')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
<div id="footer-wrap">
	<div id="footer">
		<div id="footer-logo">
			<a href="/"><img src="/images/footer-logo.png" alt="The Digital Bible Platform" /></a>
		</div>
		<div id="copyrights">
			<p>
				© 2020 <a href="http://faithcomesbyhearing.com" target="_blank">Faith Comes By Hearing</a>.
			</p>
		</div>
		<div id="footer-nav">
			<ul>
				<li>
					<a href="/eula">Terms & Conditions</a>
				</li>
				<li>
	    			<a href="mailto:support@digitalbibleplatform.com">Support</a>
				</li>
				<li>
					<a href="/about">About</a>
				</li>			
			</ul>
		</div>
	</div>
</div>
@endsection