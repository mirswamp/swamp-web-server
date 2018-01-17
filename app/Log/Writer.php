<?php
/******************************************************************************\
|                                                                              |
|                                  Writer.php                                  |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This class extends the Illuminate\Log\Writer class to override        |
|        the writeLog() method so that additional SWAMP-specific infomration   |
|        gets added to each Log message.                                       |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Log;

use Illuminate\Log\Writer as BaseWriter;
use Illuminate\Support\Facades\Session;

class Writer extends BaseWriter {

	/**
   * Write a message to Monolog, appending extra SWAMP info to the 
	 * $context array.
	 *
	 * @param  string  $level
	 * @param  string  $message
	 * @param  array  $context
	 * @return void
	 */

	protected function writeLog($level, $message, $context) {
		$extra = array(); // Extra context to add to every message

		// Add SWAMP-specific info from session
		$sess = array(
			'user_uid',
		);
		foreach ($sess as $value) {
			if (Session::has($value)) {
				$sessget = Session::get($value);
				if (strlen($sessget) > 0) {
					$extra[$value] = $sessget;
				}
			}
		}

		// Add certain HTTP headers if available
		$envs = array(
			'REMOTE_ADDR',
			'REMOTE_USER',
		);
		foreach ($envs as $value) {
			if ((isset($_SERVER[$value])) && (strlen($_SERVER[$value]) > 0)) {
				$extra[$value] = $_SERVER[$value];
			}
		}

		// Add the calling function's file and line number, 2 up the stack
		$backtrace = debug_backtrace(0,3);
		if ((isset($backtrace[2])) &&
			(isset($backtrace[2]['file'])) &&
			(isset($backtrace[2]['line']))) {
			$extra['called_from'] = $backtrace[2]['file'] .':'. $backtrace[2]['line'];
		}

		// Merge the incoming array with the extra info array
		$context = array_merge($context,$extra);

		parent::writeLog($level, $message, $context);
	}

}
