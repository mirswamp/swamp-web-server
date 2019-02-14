<?php 
/******************************************************************************\
|                                                                              |
|                                  HTCondor.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a class that provides HTCondor utilities                 |
|                                                                              |
|        Author(s): Thomas Jay Anthony Bricker                                 |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Services;

use Illuminate\Support\Facades\Config;

class HTCondor {
	public static function get_condor_env_command() {
		$command = '';
		$HTCONDOR_ROOT = Config::get('app.htcondorroot');
		if ($HTCONDOR_ROOT) {
			$command = '. ' . $HTCONDOR_ROOT . '/condor.sh; '; 
		}
		return $command;
	}
}
