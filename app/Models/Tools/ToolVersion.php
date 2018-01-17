<?php
/******************************************************************************\
|                                                                              |
|                                ToolVersion.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a particular version of a tool.               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\TimeStamps\UserStamped;
use App\Models\Tools\Tool;

class ToolVersion extends UserStamped {

	// database attributes
	//
	protected $connection = 'tool_shed';
	protected $table = 'tool_version';
	protected $primaryKey = 'tool_version_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'tool_version_uuid',
		'tool_uuid',
		'platform_uuid',
		
		'version_string',
		'version_no',
		'release_date',
		'retire_date',
		'notes',

		'tool_path',
		'checksum',
		'tool_executable',
		'tool_arguments',
		'tool_directory'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'tool_version_uuid',
		'tool_uuid',
		'platform_uuid',
		'package_type_names',
		
		'version_string',
		'version_no',
		'release_date',
		'retire_date',
		'notes'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'package_type_names'
	];

	//
	// accessor methods
	//

	public function getPackageTypeNamesAttribute() {
		$names = [];
		$toolLanguages = ToolLanguage::where('tool_version_uuid', '=', $this->tool_version_uuid)->get();
		for ($i = 0; $i < sizeOf($toolLanguages); $i++) {
			array_push($names, $toolLanguages[$i]->package_type_name);
		}
		return $names;
	}

	//
	// querying methods
	//

	function getTool() {
		return Tool::where('tool_uuid', '=', $this->tool_uuid)->first();
	}

	//
	// access control methods
	//

	public function isOwnedBy($user) {
		return $this->getTool()->isOwnedBy($user);
	}

	public function isReadableBy($user) {
		$tool = $this->getTool();
		if ($tool->isPublic() || ($tool->isProtected() && $tool->isRestricted())) {
			return true;
		} else if ($user && $user->isAdmin()) {
			return true;
		} else if ($user && $tool->isOwnedBy($user)) {
			return true;
		} else if ($user && $tool->isSharedBy($user)) {
			return true;
		} else {
			return false;
		}
	}

	public function isWriteableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else {
			return false;
		}
	}
}