{{--
    site note: this file is referenced in resources/views/layouts/app.blade.php.
    this is the main shell definition  for site navigation

--}}
    <div id="header" class="user">
        <div id="logo">
					<a href="/"><img src="/images/logo.svg" style="height:44px; fill:#ca4316;" onerror="this.onerror=null; this.src='logo.png'" alt="The Digital Bible Platform" /></a>
		</div>
			<div id="nav">
				<ul>
					<li>
						<a href="/docs" class="current">Developer Docs</a>
					</li>

   					<li>
						<a href="/signup">Sign Up</a>
					</li>
					<li>
						<a href="/site/wp-login.php" class="sign-up">Log In</a>
					</li>
                                        					
				</ul>
			</div>    
	</div><!--end header-->
