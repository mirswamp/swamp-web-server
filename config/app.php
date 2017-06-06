<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Application Debug Mode
	|--------------------------------------------------------------------------
	|
	| When your application is in debug mode, detailed error messages with
	| stack traces will be shown on every error that occurs within your
	| application. If disabled, a simple generic error page is shown.
	|
	*/

	'debug' => env('APP_DEBUG', false),

	/*
	|--------------------------------------------------------------------------
	| Application URL
	|--------------------------------------------------------------------------
	|
	| This URL is used by the console to properly generate URLs when using
	| the Artisan command line tool. You should set this to the root of
	| your application so that it is used when running Artisan tasks.
	|
	*/

	'url' => env('APP_URL', 'http://localhost/swamp-web-server/public'),

	/*
	|--------------------------------------------------------------------------
	| CORS Application URL
	|--------------------------------------------------------------------------
	|
	| This URL is used by the console to properly generate CORS urls.
	|
	*/

	'cors_url' => env('APP_CORS_URL', 'http://localhost/www-front-end'),

	/*
	|--------------------------------------------------------------------------
	| GitHub Authentication
	|--------------------------------------------------------------------------
	|
	| This is whether or not to display and use GitHub authentication.
	|
	*/

	'github_authentication_enabled' => env('GITHUB_ENABLED', false),

	/*
	|--------------------------------------------------------------------------
	| Google Authentication
	|--------------------------------------------------------------------------
	|
	| This is whether or not to display and use GitHub authentication.
	|
	*/

	'google_authentication_enabled' => env('GOOGLE_ENABLED', false),

	/*
	|--------------------------------------------------------------------------
	| CI Logon Authentication
	|--------------------------------------------------------------------------
	|
	| This is whether or not to display and use federated authentication.
	|
	*/

	'ci_logon_authentication_enabled' => env('CILOGON_ENABLED', false),

	/*
	|--------------------------------------------------------------------------
	| API Explorer
	|--------------------------------------------------------------------------
	|
	| This is whether or not to display the API explorer.
	|
	*/

	'api_explorer_enabled' => env('API_EXPLORER_ENABLED', false),

	/*
	|--------------------------------------------------------------------------
	| Floodlight Host URL
	|--------------------------------------------------------------------------
	|
	| The hostname or IP of the floodlight server.
	|
	*/

	'floodlight' => env('APP_FLOODLIGHT'),

	/*
	|--------------------------------------------------------------------------
	| Name Server IP
	|--------------------------------------------------------------------------
	|
	| The server used to look up the IPv4 address of running iVMs.
	|
	*/

	'nameserver' => env('APP_NAMESERVER'),

    /*  
    |--------------------------------------------------------------------------
    | HTCondor Collector Hostname
    |--------------------------------------------------------------------------
    |
    | The server used to obtain vm status information
    |
    */

    'htcondorcollectorhost' => env('HTCONDOR_COLLECTOR_HOST'),


	/*
	|--------------------------------------------------------------------------
	| Promotional Code
	|--------------------------------------------------------------------------
	|
	| This is whether or not to display and use a promotional code
	|
	*/

	'use_promo_code' => env('USE_PROMO_CODE', false),

	/*
	|--------------------------------------------------------------------------
	| File Uploading
	|--------------------------------------------------------------------------
	|
	| This path is used for incoming file uploads.
	|
	*/

	'incoming' => env('APP_INCOMING', '/swamp/incoming/'),

	/*
	|--------------------------------------------------------------------------
	| Results
	|--------------------------------------------------------------------------
	|
	| This path is used for incoming file uploads.
	|
	*/

	'outgoing' => env('APP_OUTGOING', '/swamp/outgoing/'),

	/*
	|--------------------------------------------------------------------------
	| Password Encryption
	|--------------------------------------------------------------------------
	|
	| This setting determines the algorithm used by the application to encrypt 
	| passwords.  Note:  If password encryption is handled by LDAP instead of
	| the application code, then this value should be set to NONE. 
	|
	*/

	'password_encryption_method' => env('APP_PASSWORD_ENCRYPTION_METHOD', 'BCRYPT'),

	/*
	|--------------------------------------------------------------------------
	| App Passwords
	|--------------------------------------------------------------------------
	|
	| App Passwords for alternative login. 0 (or less) indicates app passwords
	| are disabled. Note there is a hardcoded max of 100 app passwords (per
	| user). Default is 10.
	|
	*/

	'app_password_max' => env('APP_PASSWORD_MAX', 10),


	/*
	|--------------------------------------------------------------------------
	| Application Route Whitelist Patterns
	|--------------------------------------------------------------------------
	|
	| This array is used to configure which route patterns will bypass all
	| route protection.  Use wisely.
	|
	*/

	'whitelist' => array(
		'login',
		'logout',
		'countries',
		'password_resets*',
		'verifications*',
		'contacts*',
		array( 
			'users*' => array(
				'get',
				'post'
			) 
		),
		array( 
			'admin_invitations*' => array(
				'get',
				'put'
			) 
		),
		array( 
			'invitations*' => array(
				'get',
				'put'
			) 
		),
		array( 
			'admins*' => array(
				'post'
			) 
		),
		array( 
			'memberships*' => array(
				'post',
				'put'
			) 
		),
		array( 
			'projects*' => array(
				'get'
			) 
		),
		'users/validate*',
		'users/email*',
		'packages/public',
		'packages/types',
		'tools/public',
		'tools/restricted',
		'platforms/public',
		'github/login',
		'idps',

		// testing routes
		//
		'environment',
		'name'
	),

	/*
	|--------------------------------------------------------------------------
	| Application Routes which do not set Session Data
	|--------------------------------------------------------------------------
	|
	| This array is used to configure which route patterns do not set session
	| information (i.e., no session cookie will be set).
	|
	*/

	'nosession' => array(
		'packages/public',
		'packages/types',
		'tools/public',
		'tools/restricted',
		'platforms/public',
		'countries',
		'idps',

		// testing routes
		//
		'environment'
	),

	/*
	|--------------------------------------------------------------------------
	| Application Timezone
	|--------------------------------------------------------------------------
	|
	| Here you may specify the default timezone for your application, which
	| will be used by the PHP date and date-time functions. We have gone
	| ahead and set this to a sensible default for you out of the box.
	|
	*/

	'timezone' => 'UTC',

	/*
	|--------------------------------------------------------------------------
	| Application Locale Configuration
	|--------------------------------------------------------------------------
	|
	| The application locale determines the default locale that will be used
	| by the translation service provider. You are free to set this value
	| to any of the locales which will be supported by the application.
	|
	*/

	'locale' => 'en',

	/*
	|--------------------------------------------------------------------------
	| Application Fallback Locale
	|--------------------------------------------------------------------------
	|
	| The fallback locale determines the locale to use when the current one
	| is not available. You may change the value to correspond to any of
	| the language folders that are provided through your application.
	|
	*/

	'fallback_locale' => 'en',

	/*
	|--------------------------------------------------------------------------
	| Encryption Key
	|--------------------------------------------------------------------------
	|
	| This key is used by the Illuminate encrypter service and should be set
	| to a random, 32 character string, otherwise these encrypted strings
	| will not be safe. Please do this before deploying an application!
	|
	*/

	'key' => env('APP_KEY', 'SomeRandomString'),

	'cipher' => MCRYPT_RIJNDAEL_128,

	/*
	|--------------------------------------------------------------------------
	| Logging Configuration
	|--------------------------------------------------------------------------
	|
	| Here you may configure the log settings for your application. Out of
	| the box, Laravel uses the Monolog PHP logging library. This gives
	| you a variety of powerful log handlers / formatters to utilize.
	|
	| Available Settings: "single", "daily", "syslog", "errorlog"
	|
	*/

	'log' => env('APP_LOGGING', 'syslog'),

	/*
	|--------------------------------------------------------------------------
	| Autoloaded Service Providers
	|--------------------------------------------------------------------------
	|
	| The service providers listed here will be automatically loaded on the
	| request to your application. Feel free to add your own services to
	| this array to grant expanded functionality to your applications.
	|
	*/

	'providers' => [

		/*
		 * Laravel Framework Service Providers...
		 */
		'Illuminate\Foundation\Providers\ArtisanServiceProvider',
		'Illuminate\Auth\AuthServiceProvider',
		'Illuminate\Bus\BusServiceProvider',
		'Illuminate\Cache\CacheServiceProvider',
		'Illuminate\Foundation\Providers\ConsoleSupportServiceProvider',
		'Illuminate\Routing\ControllerServiceProvider',
		'Illuminate\Cookie\CookieServiceProvider',
		'Illuminate\Database\DatabaseServiceProvider',
		'Illuminate\Encryption\EncryptionServiceProvider',
		'Illuminate\Filesystem\FilesystemServiceProvider',
		'Illuminate\Foundation\Providers\FoundationServiceProvider',
		'Illuminate\Hashing\HashServiceProvider',
		'Illuminate\Mail\MailServiceProvider',
		'Illuminate\Pagination\PaginationServiceProvider',
		'Illuminate\Pipeline\PipelineServiceProvider',
		'Illuminate\Queue\QueueServiceProvider',
		'Illuminate\Redis\RedisServiceProvider',
		'Illuminate\Auth\Passwords\PasswordResetServiceProvider',
		'Illuminate\Session\SessionServiceProvider',
		'Illuminate\Translation\TranslationServiceProvider',
		'Illuminate\Validation\ValidationServiceProvider',
		'Illuminate\View\ViewServiceProvider',

		/*
		 * Application Service Providers...
		 */
		'App\Providers\AppServiceProvider',
		'App\Providers\BusServiceProvider',
		'App\Providers\ConfigServiceProvider',
		'App\Providers\EventServiceProvider',
		'App\Providers\RouteServiceProvider',

	],

	/*
	|--------------------------------------------------------------------------
	| Class Aliases
	|--------------------------------------------------------------------------
	|
	| This array of class aliases will be registered when this application
	| is started. However, feel free to register as many as you wish as
	| the aliases are "lazy" loaded so they don't hinder performance.
	|
	*/

	'aliases' => [

		'App'       => 'Illuminate\Support\Facades\App',
		'Artisan'   => 'Illuminate\Support\Facades\Artisan',
		'Auth'      => 'Illuminate\Support\Facades\Auth',
		'Blade'     => 'Illuminate\Support\Facades\Blade',
		'Bus'       => 'Illuminate\Support\Facades\Bus',
		'Cache'     => 'Illuminate\Support\Facades\Cache',
		'Config'    => 'Illuminate\Support\Facades\Config',
		'Cookie'    => 'Illuminate\Support\Facades\Cookie',
		'Crypt'     => 'Illuminate\Support\Facades\Crypt',
		'DB'        => 'Illuminate\Support\Facades\DB',
		'Eloquent'  => 'Illuminate\Database\Eloquent\Model',
		'Event'     => 'Illuminate\Support\Facades\Event',
		'File'      => 'Illuminate\Support\Facades\File',
		'Hash'      => 'Illuminate\Support\Facades\Hash',
		'Input'     => 'Illuminate\Support\Facades\Input',
		'Inspiring' => 'Illuminate\Foundation\Inspiring',
		'Lang'      => 'Illuminate\Support\Facades\Lang',
		'Log'       => 'Illuminate\Support\Facades\Log',
		'Mail'      => 'Illuminate\Support\Facades\Mail',
		'Password'  => 'Illuminate\Support\Facades\Password',
		'Queue'     => 'Illuminate\Support\Facades\Queue',
		'Redirect'  => 'Illuminate\Support\Facades\Redirect',
		'Redis'     => 'Illuminate\Support\Facades\Redis',
		'Request'   => 'Illuminate\Support\Facades\Request',
		'Response'  => 'Illuminate\Support\Facades\Response',
		'Route'     => 'Illuminate\Support\Facades\Route',
		'Schema'    => 'Illuminate\Support\Facades\Schema',
		'Session'   => 'Illuminate\Support\Facades\Session',
		'Storage'   => 'Illuminate\Support\Facades\Storage',
		'URL'       => 'Illuminate\Support\Facades\URL',
		'Validator' => 'Illuminate\Support\Facades\Validator',
		'View'      => 'Illuminate\Support\Facades\View',

	],

];
