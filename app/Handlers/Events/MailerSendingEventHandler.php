<?php
/******************************************************************************\
|                                                                              |
|                        MailerSendingEventHandler.php                         |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a handler for the 'mailer.sending' event which is        |
|        triggered any time a mail message is sent. See                        |
|        App\Providers\EventServiceProvider for linking the event to this      |
|        handler.                                                              |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Handlers\Events;

use Swift_Message;
use Illuminate\Support\Facades\Log;

class MailerSendingEventHandler {

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
	public function __construct() {
			//
	}

	/**
	 * Handle the 'mailer.sending' event. This function takes a Swift_Message
	 * object and logs the relevant details of the mail message, namely
	 * mail message ID, recipients, and subject.
	 *
	 * @param	Swift_Message	$message
	 * @return void
	 */
	public function handle(Swift_Message $message) {
		$context = array();

		// Add the mail message ID
		$msgid = $message->getId();
		if (strlen($msgid) > 0) {
			$context['Message-Id'] = $msgid;
		}

		// Add recipients' names and email addresses
		$msgto = $message->getTo();  // This is an array of emailaddr => name
		if (count($msgto) > 0) {
			$addrs = '';
			foreach ($msgto as $email => $name) {
				$addrs .= "$name <$email>,";
			}
			$addrs = rtrim($addrs,','); // Remove last comma
			$context['To'] = $addrs;
		}

		// Add the subject
		$msgsubj = $message->getSubject();
		if (strlen($msgsubj) > 0) {
			$context['Subject'] = $msgsubj;
		}

		Log::info("SWAMP mail sent.",$context);
	}

}
