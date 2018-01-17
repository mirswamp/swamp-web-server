<?php 
/******************************************************************************\
|                                                                              |
|                             BeforeMiddleware.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware for before requests are serviced.             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use App\Utilities\Sanitization\Sanitize;
use Closure;

class BeforeMiddleware {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// sanitize input
		//
		$impure = false;
		$input = Input::all();
		$bannedInput = [];
		$keys = array_keys($input);
		for ($i = 0; $i < sizeof($keys); $i++) {

			// get input key value pair
			//
			$key = $keys[$i];
			$value = $input[$key];

			// sanitize values
			//
			if (gettype($value) == 'string') {

				// use appropriate filtering method
				//
				if ($key != 'password' && $key != 'new_password' && $key != 'old_password') {

					// detect impure input
					//
					if ($value != Sanitize::purify($value)) {
						$impure = true;
						$bannedInput[$key] = $input[$key];
					}
				} else {

					// strip script tags only
					//
					$input[$key] = str_ireplace("<script>", "", $input[$key]);
				}
			}
		}

		if ($impure) {

			// report banned input
			//
			$userUid = session('user_uid');
			syslog(LOG_WARNING, "User $userUid attempted to send unsanitary input containing HTML tags or script: ".json_encode($bannedInput));

			// return error
			//
			// return Response::make("Can not send unsanitary input containing HTML tags or script.", 400);
		}

		// return sanitized input
		//
		Input::replace($input);

		return $next($request);
	}

}
