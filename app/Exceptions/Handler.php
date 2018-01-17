<?php namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Config;

class Handler extends ExceptionHandler {

	/**
	 * A list of the exception types that should not be reported.
	 *
	 * @var array
	 */
	protected $dontReport = [
		'Symfony\Component\HttpKernel\Exception\HttpException'
	];

	/**
	 * Report or log an exception.
	 *
	 * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
	 *
	 * @param  \Exception  $e
	 * @return void
	 */
	public function report(Exception $e)
	{
		return parent::report($e);
	}

	/**
	 * Render an exception into an HTTP response.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Exception  $e
	 * @return \Illuminate\Http\Response
	 */
	public function render($request, Exception $e)
	{
		if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {

			// redirect to custom 404 page
			//
			return Redirect::to(config('app.cors_url').'/404.html');
		} else {

			// display whoops error info
			//
			if (config('app.debug')) {
				$whoops = new \Whoops\Run;
				$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);

				return response($whoops->handleException($e),
					$e->getStatusCode(),
					$e->getHeaders()
				);
			}

			return parent::render($request, $e);
		}
	}

}