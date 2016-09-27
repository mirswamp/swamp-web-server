<?php
/******************************************************************************\
|                                                                              |
|                             ViewersController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for viewers.                                |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Viewers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use App\Utilities\Uuids\Guid;
use App\Models\Viewers\Viewer;
use App\Models\Viewers\ProjectDefaultViewer;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class ViewersController extends BaseController {

	// get by index
	//
	public function getIndex($viewerUuid) {
		return Viewer::where('viewer_uuid', '=', $viewerUuid)->first();
	}

	public function getDefaultViewer($projectUid) {
		$default = ProjectDefaultViewer::where('project_uuid', '=', $projectUid)->first();
		return $default ?
			Viewer::where('viewer_uuid', '=', $default->viewer_uuid)->first() :
			Viewer::where('name', '=', 'Native')->first();
	}

	public function setDefaultViewer( $projectUuid, $viewerUuid ) {
		$default = ProjectDefaultViewer::where('project_uuid', '=', $projectUuid)->first();
		if( $default ){
			$default->viewer_uuid = $viewerUuid;
			$default->save();
		} else {
			$default = ProjectDefaultViewer::create(array( 
				'project_uuid' => $projectUuid,
				'viewer_uuid'  => $viewerUuid
			));
		}
		return $default;
	}

	public function setDefault($projectUuid, $viewerUuid) {
	}

	// get all
	//
	public function getAll() {
		$viewers = Viewer::all();
		foreach( $viewers as $viewer ){
			unset( $viewer->update_user );
			unset( $viewer->create_user );
			unset( $viewer->viewer_owner_uuid );
		}
		return $viewers;
	}

	//
	public function updateIndex($viewerUuid) {
	}

	// update multiple
	//
	public function updateAll() {

	}

	// delete by index
	//
	public function deleteIndex($viewerUuid) {

	}

}