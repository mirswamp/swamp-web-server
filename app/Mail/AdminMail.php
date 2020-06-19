<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdminMail extends Mailable
{
	use Queueable, SerializesModels;

	//
	// attributes
	//

	public $user;
	public $subject;
	public $body;

	/**
	 * Create a new message instance.
	 *
	 * @return void
	 */
	public function __construct($user, $subject, $body)
	{
		$this->user = $user;
		$this->subject = $subject;
		$this->body = $body;
	}

	/**
	 * Determine if a message contains a PGP signature.
	 *
	 * @return $bool
	 */
	public function isSecure() {
		return (strpos($this->body, 'END PGP SIGNATURE') != false) || (strpos($this->body, 'END GPG SIGNATURE') != false);
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build()
	{
		return $this->subject($this->subject)
			->from($this->isSecure()? config('mail.security.address') : config('mail.from.address'))
			->text('emails.admin')
			->with([
				'user' => $this->user,
				'body' => $this->body
			]);
	}
}
