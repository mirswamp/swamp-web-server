<?php 
/******************************************************************************\
|                                                                              |
|                              VerifyUserClass.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a user class.                       |
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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\Users\User;
use App\Models\Users\UserClass;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyUserClass {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// parse parameters
		//
		$classCode = Input::get('class_code');

		// check class code
		//
		if ($classCode) {
			if (!UserClass::where('class_code', '=', $classCode)->exists()) {
				return response('User class not found.', 404);
			}
		}

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
