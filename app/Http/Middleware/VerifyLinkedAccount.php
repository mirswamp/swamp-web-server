<?php 
/******************************************************************************\
|                                                                              |
|                           VerifyLinkedAccount.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a linked account.                   |
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
use Illuminate\Support\Facades\Input;
use App\Models\Users\LinkedAccount;
use App\Utilities\Filters\FiltersHelper;

class VerifyLinkedAccount
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
		/*
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;
			case 'get':
			case 'put':
			case 'delete':
				$linkedAccountId = $request->route('user_permission_id');
				if ($linkedAccountId) {
					$linkedAccount = LinkedAccount::where('linked_account_uid', '=', $linkedAccountId);
					if (!$linkedAccount) {
						return response('Linked account not found.', 404);
					}	
				}
				break;
		}
		*/

		return $next($request);
	}
}