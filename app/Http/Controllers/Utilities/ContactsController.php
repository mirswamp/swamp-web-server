<?php
/******************************************************************************\
|                                                                              |
|                            ContactsController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for contacts.                               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Utilities\Contact;
use App\Http\Controllers\BaseController;

class ContactsController extends BaseController {

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$data = [
			'first_name' => Input::get('first_name'),
			'last_name' => Input::get('last_name'),
			'email' => Input::get('email'),
			'subject' => Input::get('subject'),
			'question' => Input::get('question')
		];
		$topic = Input::get('topic');

		// send contact email
		//
		if (config('mail.enabled')) {
			if ($topic == 'security') {

				// send report incident email
				//
				Mail::send('emails.security', $data, function($message) use ($data) {
					$message->to(config('mail.security.address'), config('mail.security.name'));
					$message->subject($data['subject']);
				});
			} else {

				// send general contact email
				//
				Mail::send('emails.contact', $data, function($message) use ($data) {
					$message->to(config('mail.contact.address'), config('mail.contact.name'));
					$message->subject($data['subject']);
				});
			}
		} else {
			return response('Email has not been enabled.', 400); 
		}

		return $data;
	}
}
