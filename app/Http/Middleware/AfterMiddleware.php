<?php 
/******************************************************************************\
|                                                                              |
|                             AfterMiddleware.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware for after requests are serviced.              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AfterMiddleware {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$response = $next($request);

		// allow wildcard CORS origin
		//
		$response->headers->set('Access-Control-Allow-Origin', $request->header('Origin'));

		// set response headers
		//
		$response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
		$response->headers->set('Access-Control-Allow-Headers', 'x-requested-with,Content-Type,If-Modified-Since,If-None-Match,Auth-User-Token');
		$response->headers->set('Access-Control-Allow-Credentials', 'true');
		$response->headers->remove('Cache-Control');
	
		return $response;
	}

}
