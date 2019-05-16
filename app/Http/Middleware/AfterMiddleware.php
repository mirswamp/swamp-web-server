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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AfterMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// set CORS headers if neccessary
		//
		if (config('app.cors_url') && config('app.cors_url') != config('app.url')) {

			// allow requests from origin, equivalent to origin of '*'
			//
			/*
			return $next($request)->withHeaders([
				'Access-Control-Allow-Origin' => $request->header('Origin'),
				'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
				'Access-Control-Allow-Headers' => 'x-requested-with,Content-Type,If-Modified-Since,If-None-Match,Auth-User-Token',
				'Access-Control-Allow-Credentials' => 'true'
			]);
			*/

			// add headers individually rather than as a group because when
			// downloading files, the response type is a BinaryFileResponse, 
			// which does not support the 'withHeaders' method.
			//
 			$response = $next($request);
			$response->headers->set('Access-Control-Allow-Origin', $request->header('Origin'));
			$response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			$response->headers->set('Access-Control-Allow-Headers', 'x-requested-with,Content-Type,If-Modified-Since,If-None-Match,Auth-User-Token');
			$response->headers->set('Access-Control-Allow-Credentials', 'true');
			return $response;
		} else {
			return $next($request);
		}
	}
}