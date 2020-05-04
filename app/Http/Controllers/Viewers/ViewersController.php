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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Viewers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Utilities\Uuids\Guid;
use App\Models\Viewers\Viewer;
use App\Models\Viewers\ProjectDefaultViewer;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class ViewersController extends BaseController
{
	//
	// querying methods
	//

	// get by index
	//
	public function getIndex($viewerUuid): ?Viewer {
		return Viewer::find($viewerUuid);
	}

	// get all
	//
	public function getAll(): Collection {
		$defaultViewer = config('app.default_viewer');
		if ($defaultViewer && Viewer::where('name', '=', $defaultViewer)->exists()) {
			$first = Viewer::where('name', '=', $defaultViewer)->first();
			$collection = Viewer::where('name', '!=', $defaultViewer)->get();
			return $collection->prepend($first);
		} else {
			return Viewer::all();
		}
	}

	// get default
	//
	public function getDefaultViewer($projectUid): ?Viewer {
		$default = ProjectDefaultViewer::where('project_uuid', '=', $projectUid)->first();
		return $default ?
			Viewer::where('viewer_uuid', '=', $default->viewer_uuid)->first() :
			Viewer::where('name', '=', 'Native')->first();
	}
}
