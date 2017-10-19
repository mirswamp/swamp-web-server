<?php namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel {

	/**
	 * The bootstrap classes for the application.
	 * Override the base ConfigureLogging so that additional information is
	 * added to every Log::... message.
	 *
	 * @var array
	 */
	protected $bootstrappers = [
		'Illuminate\Foundation\Bootstrap\DetectEnvironment',
		'Illuminate\Foundation\Bootstrap\LoadConfiguration',
		'App\Bootstrap\ConfigureLogging',
		'Illuminate\Foundation\Bootstrap\HandleExceptions',
		'Illuminate\Foundation\Bootstrap\RegisterFacades',
		'Illuminate\Foundation\Bootstrap\RegisterProviders',
		'Illuminate\Foundation\Bootstrap\BootProviders',
	];

	/**
	 * The application's global HTTP middleware stack.
	 *
	 * @var array
	 */
	protected $middleware = [
		'Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode',
		'Illuminate\Cookie\Middleware\EncryptCookies',
		'Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse',
		// Use a custom StartSession to clean out cookies/sessions
		//'Illuminate\Session\Middleware\StartSession',
		'App\Http\Middleware\StartSession',
		'App\Http\Middleware\CookieCleaner',
		'Illuminate\View\Middleware\ShareErrorsFromSession',
		'App\Http\Middleware\VerifyCsrfToken',
		'App\Http\Middleware\BeforeMiddleware',
		'App\Http\Middleware\AfterMiddleware',
	];

	/**
	 * The application's route middleware.
	 *
	 * @var array
	 */
	protected $routeMiddleware = [
		'auth' => 'App\Http\Middleware\Authenticate',
		'auth.basic' => 'Illuminate\Auth\Middleware\AuthenticateWithBasicAuth',
		'guest' => 'App\Http\Middleware\RedirectIfAuthenticated',

		// custom middleware
		//
		'verify.config' => 'App\Http\Middleware\VerifyConfig',
		'verify.user' => 'App\Http\Middleware\VerifyUser',
		'verify.app_passwords' => 'App\Http\Middleware\VerifyAppPasswords',
		'verify.admin' => 'App\Http\Middleware\VerifyAdmin',
		'verify.admin_invitation' => 'App\Http\Middleware\VerifyAdminInvitation',
		'verify.password_reset' => 'App\Http\Middleware\VerifyPasswordReset',
		'verify.email_verification' => 'App\Http\Middleware\VerifyEmailVerification',
		'verify.policy' => 'App\Http\Middleware\VerifyPolicy',
		'verify.user_permission' => 'App\Http\Middleware\VerifyUserPermission',
		'verify.linked_account' => 'App\Http\Middleware\VerifyLinkedAccount',
		'verify.project' => 'App\Http\Middleware\VerifyProject',
		'verify.project_invitation' => 'App\Http\Middleware\VerifyProjectInvitation',
		'verify.project_membership' => 'App\Http\Middleware\VerifyProjectMembership',
		'verify.package' => 'App\Http\Middleware\VerifyPackage',
		'verify.package_version' => 'App\Http\Middleware\VerifyPackageVersion',
		'verify.tool' => 'App\Http\Middleware\VerifyTool',
		'verify.tool_version' => 'App\Http\Middleware\VerifyToolVersion',
		'verify.platform' => 'App\Http\Middleware\VerifyPlatform',
		'verify.platform_version' => 'App\Http\Middleware\VerifyPlatformVersion',
		'verify.assessment_run' => 'App\Http\Middleware\VerifyAssessmentRun',
		'verify.run_request' => 'App\Http\Middleware\VerifyRunRequest',
		'verify.run_request_schedule' => 'App\Http\Middleware\VerifyRunRequestSchedule',
		'verify.execution_record' => 'App\Http\Middleware\VerifyExecutionRecord',
		'verify.assessment_result' => 'App\Http\Middleware\VerifyAssessmentResult',
		'verify.viewer' => 'App\Http\Middleware\VerifyViewer'
	];

}
