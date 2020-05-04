<?php
/******************************************************************************\
|                                                                              |
|                        DatabaseSessionHandler.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This is an extension to the default session handler which is          |
|        provided in order to save the user_id in the session database.        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Session;

use Illuminate\Support\Facades\Session;

class DatabaseSessionHandler extends \Illuminate\Session\DatabaseSessionHandler
{
	public function write($sessionId, $data)
	{
		// $user_id = (auth()->check()) ? auth()->user()->id : null;
		$user_id = Session::get('user_uid');

		if ($user_id) {
			if ($this->exists) {

				// get existing session record
				//
				$this->getQuery()->where('id', $sessionId)->update([
					'payload' => base64_encode($data), 
					'last_activity' => time(), 
					'user_id' => $user_id
				]);
			} else {

				// create new session record
				//
				$this->getQuery()->insert([
					'id' => $sessionId, 
					'user_id' => $user_id,
					'ip_address' => request()->ip(),
					'user_agent' => request()->server('HTTP_USER_AGENT'),
					'payload' => base64_encode($data), 
					'last_activity' => time()
				]);
			}

			$this->exists = true;
		} else {

			// delete session record
			//
			$this->getQuery()->where('id', $sessionId)->delete();
		}
	}
}