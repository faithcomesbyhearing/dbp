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
                     <li class="page_item"><a href="{{ route('legal_overview')}}">Overview</a></li>
                     <li class="page_item"><a href="{{ route('legal_license')}}">License</a></li>              
                     <li class="page_item"><a href="{{ route('legal_terms')}}">Terms & Conditions</a></li>    
                     <li class="page_item"><a href="{{ route('privacy_policy')}}">Privacy Policy</a></li>      
                </div>
                <div class="legal">
                    @yield('legal-content')
					<div class="clear"></div>
                </div> <!-- end legal -->
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
				<a href="{{ route('privacy_policy')}}">Privacy Policy</a>
				</li>	
			</ul>
		</div>
	</div>
</div>
@endsection