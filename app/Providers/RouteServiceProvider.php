<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes('routes/users.php');
        $this->mapApiRoutes('routes/projects.php');
        $this->mapApiRoutes('routes/packages.php');
        $this->mapApiRoutes('routes/tools.php');
        $this->mapApiRoutes('routes/platforms.php');
        $this->mapApiRoutes('routes/assessments.php');
        $this->mapApiRoutes('routes/results.php');
        $this->mapApiRoutes('routes/events.php');
        $this->mapApiRoutes('routes/api.php');
        $this->mapApiRoutes('routes/admin.php');
        // $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes($path)
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path($path));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes($path)
    {
        Route::prefix('')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path($path));
    }
}
