<?php 
/******************************************************************************\
|                                                                              |
|                          EventServiceProvider.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines events to listen for and their associated handlers.      |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider {

	/**
	 * The event handler mappings for the application.
	 *
	 * @var array
	 */
	protected $listen = [
		// Note that this event will probably need to change when Laravel is
		// updated to 5.2/5.3 to "Illuminate\Mail\Events\MessageSending".
		'mailer.sending' => [
			'App\Handlers\Events\MailerSendingEventHandler',
		],
	];

	/**
	 * Register any other events for your application.
	 *
	 * @param  \Illuminate\Contracts\Events\Dispatcher  $events
	 * @return void
	 */
	public function boot(DispatcherContract $events)
	{
		parent::boot($events);

		//
	}

}
