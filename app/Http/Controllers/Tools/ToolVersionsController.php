<?php
/******************************************************************************\
|                                                                              |
|                         ToolVersionsController.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for tool versions.                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Tools;

use PDO;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Utilities\Files\Filename;
use App\Models\Tools\ToolVersion;
use App\Http\Controllers\BaseController;

class ToolVersionsController extends BaseController {

	// upload
	//
	public function postUpload() {	

		if (Input::hasFile('file')) {
			$file = Input::file('file');

			// query uploaded file
			//
			$filename = $file->getClientOriginalName();
			$path = $file->getRealPath();
			$extension = $file->getClientOriginalExtension();
			$mime = $file->getMimeType();
			$size = $file->getSize();

			// replace spaces in filename with dashes
			//
			$filename = Filename::sanitize($filename);

			// move file to destination
			//
			// $destinationFolder = str_random(8);
			$destinationFolder = Guid::create();
			$destinationPath = '/swamp/incoming/'.$destinationFolder;
			$uploadSuccess = Input::file('file')->move($destinationPath, $filename);
			 
			if ($uploadSuccess) {
				return response([
					'filename' => $filename,
					'path' => $path,
					'extension' => $extension,
					'mime' => $mime,
					'size' => $size,
					'destination_path' => $destinationFolder
				], 200);
			} else {
				return response('Error moving uploaded file.', 400);
			}
		} else {
			return response('No uploaded file.', 404);
		}
	}

	// add
	//
	public function postAdd($toolVersionUuid) {
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid)->first();
		$toolPath = Input::get('tool_path');

		// create stored procedure call
		//
		$connection = DB::connection('tool_shed');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL add_tool_version(
			:toolVersionUuid, :toolPath, @returnStatus, @returnMsg);");

		// bind params
		//
		$stmt->bindParam(":toolVersionUuid", $toolVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":toolPath", $toolPath, PDO::PARAM_STR, 200);

		// set param values
		//
		$returnStatus = null;
		$returnMsg = null;

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnStatus, @returnMsg');
		$results = $select->fetchAll();
		$returnStatus = $results[0]["@returnStatus"];
		$returnMsg = $results[0]["@returnMsg"];

		// delete tool version if error
		//
		if ($returnStatus == 'ERROR') {
			$toolVersion->delete();
		}

		// remove file and directory
		//
		unlink('/swamp/incoming/'.$toolPath);
		rmdir(dirname('/swamp/incoming/'.$toolPath));

		// return values
		//
		return response( $returnMsg, $returnStatus == 'ERROR' ? 500 : 200 );
	}

	// create
	//
	public function postCreate() {
		$toolVersion = new ToolVersion([
			'tool_version_uuid' => Guid::create(),
			'tool_uuid' => Input::get('tool_uuid'),
			'version_string' => Input::get('version_string'),

			'release_date' => Input::get('release_date'),
			'retire_date' => Input::get('retire_date'),
			'notes' => Input::get('notes'),

			'tool_path' => Input::get('tool_path'),
			'tool_executable' => Input::get('tool_executable'),
			'tool_arguments' => Input::get('tool_arguments'),
			'tool_directory' => Input::get('tool_directory')
		]);
		$toolVersion->save();
		return $toolVersion;
	}

	// get by index
	//
	public function getIndex($toolVersionUuid) {
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid)->first();
		return $toolVersion;
	}

	// update by index
	//
	public function updateIndex($toolVersionUuid) {

		// get model
		//
		$toolVersion = $this->getIndex($toolVersionUuid);

		// update attributes
		//
		$toolVersion->tool_version_uuid = Input::get('tool_version_uuid');
		$toolVersion->tool_uuid = Input::get('tool_uuid');
		$toolVersion->version_string = Input::get('version_string');

		$toolVersion->release_date = Input::get('release_date');
		$toolVersion->retire_date = Input::get('retire_date');
		$toolVersion->notes = Input::get('notes');

		$toolVersion->tool_path = Input::get('tool_path');
		$toolVersion->tool_executable = Input::get('tool_executable');
		$toolVersion->tool_arguments = Input::get('tool_arguments');
		$toolVersion->tool_directory = Input::get('tool_directory');

		// save and return changes
		//
		$changes = $toolVersion->getDirty();
		$toolVersion->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex($toolVersionUuid) {
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid)->first();
		$toolVersion->delete();
		return $toolVersion;
	}
}
