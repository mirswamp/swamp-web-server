<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        /*
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\TrustProxies::class,
        */

        'Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode',
        // 'Illuminate\Cookie\Middleware\EncryptCookies',
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
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            // \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // 'throttle:60,1',
            // 'bindings',
            // \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        /*
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        */

        'auth' => 'App\Http\Middleware\Authenticate',
        'auth.basic' => 'Illuminate\Auth\Middleware\AuthenticateWithBasicAuth',
        'guest' => 'App\Http\Middleware\RedirectIfAuthenticated',
        'verify.config' => 'App\Http\Middleware\VerifyConfig',
        'verify.user' => 'App\Http\Middleware\VerifyUser',
        'verify.app_passwords' => 'App\Http\Middleware\VerifyAppPasswords',
        'verify.admin' => 'App\Http\Middleware\VerifyAdmin',
        'verify.admin_invitation' => 'App\Http\Middleware\VerifyAdminInvitation',
        'verify.password_reset' => 'App\Http\Middleware\VerifyPasswordReset',
        'verify.email_verification' => 'App\Http\Middleware\VerifyEmailVerification',
        'verify.policy' => 'App\Http\Middleware\VerifyPolicy',
        'verify.user_permission' => 'App\Http\Middleware\VerifyUserPermission',
        'verify.user_class' => 'App\Http\Middleware\VerifyUserClass',
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
