<?php

/******************************************************************************\
|                                                                              |
|                                  tools.php                                   |
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
// public tool routes
//

Route::get('tools/public', 'Tools\ToolsController@getPublic');
Route::get('tools/restricted', 'Tools\ToolsController@getRestricted');

//
// authenticated tool routes
//

Route::group(['middleware' => 'auth'], function () {

	// tool routes
	//
	Route::group(['middleware' => 'verify.tool'], function () {
		Route::post('tools', 'Tools\ToolsController@postCreate');
		Route::get('tools/protected/{project_uuid}', 'Tools\ToolsController@getProtected');
		Route::get('tools/all', 'Tools\ToolsController@getAll');
		Route::get('tools/users/{user_uuid}', 'Tools\ToolsController@getByUser');
		Route::get('tools/users/{user_uuid}/num', 'Tools\ToolsController@getNumByUser');
		Route::get('tools/projects/{project_uuid}', 'Tools\ToolsController@getByProject');
		Route::get('tools/{tool_uuid}', 'Tools\ToolsController@getIndex');
		Route::get('tools/{tool_uuid}/versions', 'Tools\ToolsController@getVersions');
		Route::get('tools/{tool_uuid}/sharing', 'Tools\ToolsController@getSharing');
		Route::get('tools/{tool_uuid}/policy', 'Tools\ToolsController@getPolicy');
		Route::post('tools/{tool_uuid}/permission', 'Tools\ToolsController@getToolPermissionStatus');
		Route::put('tools/{tool_uuid}', 'Tools\ToolsController@updateIndex');
		Route::put('tools/{tool_uuid}/sharing', 'Tools\ToolsController@updateSharing');
		Route::delete('tools/{tool_uuid}', 'Tools\ToolsController@deleteIndex');
		Route::delete('tools/{tool_uuid}/versions', 'Tools\ToolsController@deleteVersions');
	});

	// tool version routes
	//
	Route::group(['middleware' => 'verify.tool_version'], function () {
		Route::post('tools/versions', 'Tools\ToolVersionsController@postCreate');
		Route::get('tools/versions/{tool_version_uuid}', 'Tools\ToolVersionsController@getIndex');
		Route::put('tools/versions/{tool_version_uuid}', 'Tools\ToolVersionsController@updateIndex');
		Route::delete('tools/versions/{tool_version_uuid}', 'Tools\ToolVersionsController@deleteIndex');
	});
});