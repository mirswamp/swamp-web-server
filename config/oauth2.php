<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| GitHub OAuth2 Identification
	|--------------------------------------------------------------------------
	|
	| These parameters are used to identify the application to GitHub.
	| Register your client at https://github.com/settings/applications/new
	|
	*/

	'github_client_id'     => env('GITHUB_CLIENT_ID'),
	'github_client_secret' => env('GITHUB_CLIENT_SECRET'),

	/*
	|--------------------------------------------------------------------------
	| Google OAuth2 Identification
	|--------------------------------------------------------------------------
	|
	| These parameters are used to identify the application to Google.
	| Register your client at https://console.developers.google.com/
	|
	*/

	'google_client_id'     => env('GOOGLE_CLIENT_ID'),
	'google_client_secret' => env('GOOGLE_CLIENT_SECRET'),

	/*
	|--------------------------------------------------------------------------
	| CILogon OAuth2 Identification
	|--------------------------------------------------------------------------
	|
	| These parameters are used to identify the application to CILogon.
	| Register your client at https://cilogon.org/oauth2/register
	|
	*/

	'cilogon_client_id'     => env('CILOGON_CLIENT_ID'),
	'cilogon_client_secret' => env('CILOGON_CLIENT_SECRET'),
	'cilogon_skin'          => env('CILOGON_SKIN','SWAMP'),

);
