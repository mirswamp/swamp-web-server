<?php

/******************************************************************************\
|                                                                              |
|                                  events.php                                  |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This is where all of the application routes are registered.           |
|        Routes are registered by telling Laravel the URIs that it             |
|        should respond to and giving it the controller to call when           |
|        that URI is requested.                                                |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

//
// authenticated event routes
//

Route::group(['middleware' => 'auth'], function () {

	//
	// user event routes
	//

	Route::group(['middleware' => 'verify.user'], function () {

		// user event routes
		//
		Route::get('events/personal/users/{user_uid}', 'Events\PersonalEventsController@getPersonalByUser');

		// project event routes
		//
		Route::get('events/projects/users/{user_uid}', 'Events\ProjectEventsController@getByUser');
		Route::get('events/projects/users/{user_uid}/events', 'Events\ProjectEventsController@getUserProjectEvents');

		// user event counting routes
		//
		Route::get('events/users/{user_uid}/num', 'Events\PersonalEventsController@getNumByUser');
		Route::get('events/personal/users/{user_uid}/num', 'Events\PersonalEventsController@getNumPersonalByUser');

		// project event counting routes
		//
		Route::get('events/projects/users/{user_uid}/num', 'Events\ProjectEventsController@getNumByUser');
		Route::get('events/projects/users/{user_uid}/events/num', 'Events\ProjectEventsController@getNumUserProjectEvents');
	});
});