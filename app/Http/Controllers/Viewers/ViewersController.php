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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
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

	public function setDefaultViewer($projectUuid, $viewerUuid) {
		$default = ProjectDefaultViewer::where('project_uuid', '=', $projectUuid)->first();
		if ($default) {
			$default->viewer_uuid = $viewerUuid;
			$default->save();
		} else {
			$default = ProjectDefaultViewer::create([
				'project_uuid' => $projectUuid,
				'viewer_uuid'  => $viewerUuid
			]);
		}
		return $default;
	}

	public function setDefault($projectUuid, $viewerUuid) {
	}

	// get all
	//
	public function getAll() {
		$defaultViewer = config('app.default_viewer');
		if ($defaultViewer && Viewer::where('name', '=', $defaultViewer)->exists()) {
			$first = Viewer::where('name', '=', $defaultViewer)->first();
			$list = Viewer::where('name', '!=', $defaultViewer)->get();
			return $list->prepend($first);
		} else {
			return Viewer::all();
		}
	}
}
