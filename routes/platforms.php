<?php

/******************************************************************************\
|                                                                              |
|                                platforms.php                                 |
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
// public platform routes
//

Route::get('platforms/public', 'Platforms\PlatformsController@getPublic');

//
// authenticated platform routes
//

Route::group(['middleware' => 'auth'], function () {
	
	// platform routes
	//
	Route::group(['middleware' => 'verify.platform'], function () {
		Route::post('platforms', 'Platforms\PlatformsController@postCreate');
		Route::get('platforms/users/{user_uuid}', 'Platforms\PlatformsController@getByUser');
		Route::get('platforms/all', 'Platforms\PlatformsController@getAll');
		Route::get('platforms/protected/{project_uuid}', 'Platforms\PlatformsController@getProtected');
		Route::get('platforms/projects/{project_uuid}', 'Platforms\PlatformsController@getByProject');
		Route::get('platforms/{platform_uuid}', 'Platforms\PlatformsController@getIndex');
		Route::get('platforms/{platform_uuid}/versions', 'Platforms\PlatformsController@getVersions');
		Route::get('platforms/{platform_uuid}/sharing', 'Platforms\PlatformsController@getSharing');
		Route::put('platforms/{platform_uuid}', 'Platforms\PlatformsController@updateIndex');
		Route::put('platforms/{platform_uuid}/sharing', 'Platforms\PlatformsController@updateSharing');
		Route::delete('platforms/{platform_uuid}', 'Platforms\PlatformsController@deleteIndex');
		Route::delete('platforms/{platform_uuid}/versions', 'Platforms\PlatformsController@deleteVersions');
	});

	// platform version routes
	//
	Route::group(['middleware' => 'verify.platform_version'], function () {
		Route::post('platforms/versions', 'Platforms\PlatformVersionsController@postCreate');
		Route::get('platforms/versions/all', 'Platforms\PlatformVersionsController@getAll');
		Route::get('platforms/versions/{platform_version_uuid}', 'Platforms\PlatformVersionsController@getIndex');
		Route::put('platforms/versions/{platform_version_uuid}', 'Platforms\PlatformVersionsController@updateIndex');
		Route::delete('platforms/versions/{platform_version_uuid}', 'Platforms\PlatformVersionsController@deleteIndex');
	});
});