<?php
/******************************************************************************\
|                                                                              |
|                             ProjectMembership.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a user's membership within a project.         |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Projects;

use App\Models\TimeStamps\CreateStamped;

class ProjectMembership extends CreateStamped
{
	// database attributes
	//
	protected $table = 'project_user';
	protected $primaryKey = 'membership_uid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'membership_uid',
		'project_uid', 
		'user_uid',
		'admin_flag'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'membership_uid',
		'project_uid', 
		'user_uid',
		'admin_flag'
	];

	// attribute types
	//
	protected $casts = [
		'admin_flag' => 'boolean'
	];
	
	//
	// querying methods
	//

	public function isActive() {
		return (!$this->delete_date);
	}

	//
	// methods
	//

	public static function deleteByUser($user) {
		if (config('model.database.use_stored_procedures')) {

			// execute stored procedure
			//
			self::PDORemoveUserFromAllProjects($userUid);
		} else {

			// execute SQL query
			//
			self::where('user_uid', '=', $user->user_uid)->delete();
		}
	}

	//
	// PDO methods
	//

	private static function PDORemoveUserFromAllProjects($user) {

		// call stored procedure to remove all project associations
		//
		$connection = DB::connection('mysql');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL remove_user_from_all_projects(:userUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $user->user_uid, PDO::PARAM_STR, 45);
		$stmt->execute();

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();
	}
}
