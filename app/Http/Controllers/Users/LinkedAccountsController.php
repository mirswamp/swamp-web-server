<?php
/******************************************************************************\
|                                                                              |
|                         LinkedAccountsController.php                         |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for linked accounts.                        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\BaseController;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\UserPermission;
use App\Models\Users\LinkedAccount;
use App\Models\Users\Permission;
use App\Models\Users\UserEvent;
use App\Utilities\Uuids\Guid;
use App\Utilities\Identity\IdentityProvider;

class LinkedAccountsController extends BaseController {

	public function getLinkedAccountsByUser($userUid) {
		$active_user = User::getIndex(Session::get('user_uid'));
		if( $userUid == Session::get('user_uid') || $active_user->isAdmin() )
			return LinkedAccount::where('user_uid', '=', $userUid)->get();
		return response('User not allowed to retrieve linked accounts.', 401);
	}

	public function deleteLinkedAccount( $linkedAccountId ){
		$active_user 	= User::getIndex(Session::get('user_uid'));
		$account 		= LinkedAccount::where('linked_account_id', '=', $linkedAccountId)->first();
		$user 			= User::getIndex($account->user_uid);
		if( ( $user->user_uid == $active_user->user_uid ) || $active_user->isAdmin() ){
			$idp = new IdentityProvider();
			$userEvent = new UserEvent(array(
				'user_uid' => $user->user_uid,
				'event_type' => 'linkedAccountDeleted',
				'value' => json_encode(array( 
					'linked_account_provider_code' 	=> $idp->linked_provider, 
					'user_external_id' 				=> $account->user_external_id, 
					'user_ip' 						=> $_SERVER['REMOTE_ADDR']
				))
			));
			$userEvent->save();
			$account->delete();

			// Log the link delete event
			Log::info("Linked account deleted.",
				array(
					'linked_user_uid' => $user->user_uid,
					'linked_account_id' => $linkedAccountId,
					'linked_account_provider_code' => $idp->linked_provider,
					'user_external_id' => $account->user_external_id,
				)
			);

			return response('The linked account has been deleted.', 204);
		} else {
			return response('Unable to delete this linked account.  Insufficient privileges.', 400);
		}
	}

	public function setEnabledFlag( $linkedAccountId ){
		$value 			= Input::get('enabled_flag');
		$active_user 	= User::getIndex(Session::get('user_uid'));
		$account 		= LinkedAccount::where('linked_account_id', '=', $linkedAccountId)->first();
		$user 			= User::getIndex($account->user_uid);
		if( ( $user->user_uid == $active_user->user_uid ) || $active_user->isAdmin() ){
			$account->enabled_flag = $value ? 1 : 0;
			$account->save();
			$idp = new IdentityProvider();
			$userEvent = new UserEvent(array(
				'user_uid' => $user->user_uid,
				'event_type' => 'linkedAccountToggled',
				'value' => json_encode(array( 
					'linked_account_provider_code' 	=> $idp->linked_provider, 
					'user_external_id' 				=> $account->user_external_id, 
					'user_ip' 						=> $_SERVER['REMOTE_ADDR'],
					'enabled'						=> $account->enabled_flag
				))
			));
			$userEvent->save();
			return response('The status of this linked account has been updated.');
		} else {
			return response('Unable to update this linked account.  Insufficient privileges.', 400);
		}
	}
}
