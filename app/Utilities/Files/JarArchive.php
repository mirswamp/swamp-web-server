<?php
/******************************************************************************\
|                                                                              |
|                                JarArchive.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for handling archive files in jar format.      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Files;

use App\Utilities\Files\BaseArchive;
use App\Utilities\Strings\StringUtils;

class JarArchive extends BaseArchive
{
	//
	// jar specific archive methods
	//

	function getFileTypes(string $dirname = null): array {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);

		// filter for file names
		//
		$names = $this->getFileNames($names);

		// get names that are part of directory
		//
		if ($dirname && $dirname != '.' && $dirname != './') {
			$names = $this->getNamesNestedInDirectory($names, $dirname);
		}

		// return file types of names
		//
		return $this->getFileTypesFromNames($names);
	}

	function getFileInfoList(string $dirname = null, string $filter = null, bool $recursive = false, bool $trim = true): array {

		// get file names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);
		$names = $this->getFileAndDirectoryNames($names, $trim);

		// get names that are part of directory
		//
		if ($dirname) {
			$names = $this->getNamesInDirectory($names, $dirname, $recursive, $trim);
		}

		// apply filter
		//
		if ($filter) {
			$names = $this->getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return $this->namesToInfoArray($names);
	}

	function getDirectoryInfoList(string $dirname = null, string $filter = null, bool $recursive = false): array {

		// get file and directory names
		//
		$script = 'jar -tf '.$this->path;
		$names = [];
		exec($script, $names);

		// filter for directory names
		//
		$names = $this->getDirectoryNames($names, $recursive);

		// apply filter
		//
		if ($filter) {
			$names = $this->getFilteredNames($names, $filter);
		}

		// return names converted to info
		//
		return $this->namesToInfoArray($names);
	}

	public function extractTo(string $destination, array $filenames = null) {
		$tarArchive = new \PharData($this->path);
		$tarArchive->extractTo($destination, $filenames);
	}

	public function extractContents(string $filePath): ?string {

		// extract file contents
		//
		$zip = zip_open($this->path);
		$contents = null;

		if (is_resource($zip)) {

			// find entry
			//
			do {
				$entry = zip_read($zip);
			} while ($entry && zip_entry_name($entry) != $filePath);

			// open entry
			//
			if ($entry) {
				zip_entry_open($zip, $entry, "r");

				// read entry
				//
				$contents = zip_entry_read($entry, zip_entry_filesize($entry));
			}

			zip_close($zip);
		}

		return $contents;
	}
}
