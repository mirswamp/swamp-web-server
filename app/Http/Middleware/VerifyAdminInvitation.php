<?php 
/******************************************************************************\
|                                                                              |
|                          VerifyAdminInvitation.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify an administrator invitation.        |
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
use App\Models\Users\User;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyAdminInvitation
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
			case 'get':
				if (Session::has('user_uid')) {
					$user = User::current();
				}
				else {
					return response([
						'status' => 'NO_SESSION',
						'config' => new Configuration()
					], 401);
				}
				//$user = User::current();
				if ((count($request->segments()) == 1) && ((!$user) || (!$user->isAdmin()))) {
					return response('Unable to access route.  Current user is not an administrator.', 401);
				}
				break;

			case 'put':
			case 'delete':
				break;
		}

		return $next($request);
	}
}