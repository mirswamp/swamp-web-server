<?php 
/******************************************************************************\
|                                                                              |
|                                VerifyUser.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a user.                             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\Users\User;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyUser {

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
			$currentUser = User::getIndex(Session::get('user_uid'));
		} else {
			return response(array(
				'status' => 'NO_SESSION',
				'config' => new Configuration()
			), 401);
		}

		// get user
		//
		$userUid = $request->route()->getParameter('user_uid');
		if ($userUid != 'current') {
			$user = User::getIndex($userUid);
		} else {
			$user = $currentUser;
		}
		
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;

			case 'get':
				if ($user && !$user->isReadableBy($currentUser)) {
					return response('Insufficient priveleges to access user.', 403);
				}
				break;

			case 'put':
			case 'delete':
				if ($user && !$user->isWriteableBy($currentUser)) {
					return response('Insufficient priveleges to modify user.', 403);
				}
				break;
		}

		return $next($request);
	}

}
