<?php 
/******************************************************************************\
|                                                                              |
|                         VerifyEmailVerification.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify an email verification.              |
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
use Illuminate\Support\Facades\Session;
use App\Models\Users\EmailVerification;
use App\Utilities\Filters\FiltersHelper;

class VerifyEmailVerification
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
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;

			case 'get':
			case 'put':
			case 'delete':

				// verification key is last request segment
				//
				$verificationKey = $request->segment(count($request->segments()));
				$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
				if (!$emailVerification) {
					return response('Unable to access email verification', 404);
				}
				break;
		}

		return $next($request);
	}
}