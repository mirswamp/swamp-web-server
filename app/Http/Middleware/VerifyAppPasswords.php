<?php 
/******************************************************************************\
|                                                                              |
|                            VerifyAppPasswords.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a user can work with                |
|        app passwords. It is essentially the same as VerifyUser.php.          |
|                                                                              |
|        Author(s): Abe Megahed, Terry Fleury                                  |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use App\Models\Users\User;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyAppPasswords {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// get current user
		//
		if (Session::has('user_uid')) {
			$currentUser = User::getIndex(session('user_uid'));
		} else {
			return response([
				'status' => 'NO_SESSION',
				'config' => new Configuration()
			], 401);
		}

		// get user
		//
		$userUid = $request->route('user_uid');
		if ($userUid != 'current') {
			$user = User::getIndex($userUid);
		} else {
			$user = $currentUser;
		}
		
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
			case 'get':
				if ($user && !$user->isReadableBy($currentUser)) {
					return response('Insufficient priveleges to access user app passwords.', 403);
				}
				break;

			case 'put':
			case 'delete':
				if ($user && !$user->isWriteableBy($currentUser)) {
					return response('Insufficient priveleges to modify user app passwords.', 403);
				}
				break;
		}

		return $next($request);
	}

}
