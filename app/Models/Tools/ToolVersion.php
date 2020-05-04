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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\TimeStamps\TimeStamped;
use App\Models\Users\User;
use App\Models\Tools\Tool;

class ToolVersion extends TimeStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'tool_shed';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'tool_version';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'tool_version_uuid';

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'tool_version_uuid',
		'tool_uuid',
		'platform_uuid',
		
		'comment_public',
		'version_string',
		'version_no',
		'release_date',
		'retire_date',

		'tool_path',
		'checksum'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'tool_version_uuid',
		'tool_uuid',
		'platform_uuid',
		'package_type_names',
		
		'comment_public',
		'version_string',
		'version_no',
		'release_date',
		'retire_date'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
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

	public function isOwnedBy(User $user) {
		return $this->getTool()->isOwnedBy($user);
	}

	public function isReadableBy(User $user) {
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

	public function isWriteableBy(User $user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else {
			return false;
		}
	}
}