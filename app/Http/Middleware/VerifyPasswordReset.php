<?php 
/******************************************************************************\
|                                                                              |
|                           VerifyPasswordReset.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a password reset.                   |
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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use App\Models\Users\User;
use App\Models\Users\PasswordReset;
use App\Utilities\Filters\FiltersHelper;

use \DateTime;
use \DateTimeZone;

class VerifyPasswordReset {

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
			case 'get':
				break;

			case 'put':
				$user = User::getIndex(Session::get('user_uid'));
				if (!$user) {

					// not logged in
					//
					if ((!$request->has('password_reset_key')) || (!$request->has('password_reset_id'))) {
						return response('Unable to modify user.', 401);
					}

					// check for password reset
					//
					$passwordReset = PasswordReset::where('password_reset_id', '=', $request->input('password_reset_id'))->first();
					if (!$passwordReset || !Hash::check($request->input('password_reset_key'), $passwordReset->password_reset_key)) {
						return response('Unable to modify user.', 401);
					}

					// check for password reset expiration
					//
					$time = new DateTime($passwordReset->create_date, new DateTimeZone('GMT'));
					if ((gmdate('U') - $time->getTimestamp() ) > 1800) {
						return response('Password reset key expired.', 401);
					}
				} else {

					// logged in
					//
					if (($request->has('password_reset_key')) && ($request->has('password_reset_id'))) {
						break;
					}
					if (!$request->has('user_uid')) {
						return response('Unable to modify user.', 500);
					}
					if (!$user->isAdmin() && ($request->input('user_uid') != $user->user_uid)) {
						return response('Unable to modify user.', 500);
					}
				}
				break;

			case 'delete':
				break;
		}

		return $next($request);
	}

}
