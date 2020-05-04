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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
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
	public function handle(Request $request, Closure $next)
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
		} else {
			$response = $next($request);
		}

		// return only 200 status code for all 200 series status codes
		//
		if (config('app.simple_status_codes')) {
			if (method_exists($response, 'status')) {
				$status = $response->status();
				if ($status >= 200 && $status < 300) {
					$response->setStatusCode(200);
				}
			}
		}
		return $response;
	}
}