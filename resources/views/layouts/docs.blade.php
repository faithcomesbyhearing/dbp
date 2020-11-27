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
                     <li class="page_item page-item-1888"><a href="{{ route('core_concepts')}}">Core Concepts</a></li>
                     <li class="page_item"><a href="{{ route('available_content')}}">Available Content</a></li>              
                     <li class="page_item"><a href="{{ route('api_reference')}}">API Reference</a></li>    
                     <li class="page_item"><a href="{{ route('user_flows')}}">User Flows</a></li>      
                </div>
                <div class="dev-docs">
                    @yield('docs-content')
					<div class="clear"></div>
                </div> <!-- end dev-docs -->
            </div> <!-- end wrap -->
        </div>
    </div>
</div> <!-- end content -->
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
				<a href="{{ route('legal_overview')}}">Legal</a>
				</li>
				<li>
	    			<a href="mailto:support@digitalbibleplatform.com">Support</a>
				</li>
				<li>
					<a href="https://www.faithcomesbyhearing.com/privacy-policy">Privacy Policy</a>
				</li>	
			</ul>
		</div>
	</div>
</div>
@endsection