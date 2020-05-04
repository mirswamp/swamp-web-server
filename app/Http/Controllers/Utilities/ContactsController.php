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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Utilities\Contact;
use App\Http\Controllers\BaseController;

class ContactsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request) {

		// parse parameters
		//
		$data = [
			'first_name' => $request->input('first_name'),
			'last_name' => $request->input('last_name'),
			'email' => $request->input('email'),
			'subject' => $request->input('subject'),
			'question' => $request->input('question')
		];
		$topic = $request->input('topic');

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
