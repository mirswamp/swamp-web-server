<?php 
/******************************************************************************\
|                                                                              |
|                              Authenticate.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware for authentication.                           |
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
use App\Models\Users\User;
use App\Models\Utilities\Configuration;

class Authenticate {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		if (Session::has('user_uid')) {

			// get user from current session
			//
			$user_uid = Session::get('user_uid');
			if ($user_uid && $request->segment(1) != 'logout') {
				$user = User::getIndex($user_uid);

				// check to see if user is enabled
				//
				if ($user && !$user->isEnabled()) {
					Session::flush();
					return response('USER_NOT_ENABLED', 401);
				}
			} else {
				Session::flush();
				return response('SESSION_INVALID', 401);
			}	
        } elseif (Session::has('oauth2_access_token')) {

        	//if a user has an oauth2 token but is not logged in, need
        	//to allow them to be forwarded to necessary login functions
            $reqName = strval($request);
            $isCurrUsers = strpos($reqName, 'users/current');
            if ($isCurrUsers) {
            	return $next($request);
            }
            else {
				return response(array(
					'status' => 'NO_SESSION',
					'config' => new Configuration()
				), 401);            
			}
		} else {

			// no current session exists
			//
			Session::flush();
			//return response('NO_SESSION', 401);

			// return configuration information
			//
			return response(array(
				'status' => 'NO_SESSION',
				'config' => new Configuration()
			), 401);
		}

		return $next($request);
	}
}
