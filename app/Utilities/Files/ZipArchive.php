<?php
/******************************************************************************\
|                                                                              |
|                                ZipArchive.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for handling archive files in zip format.      |
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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Files;

use App\Utilities\Files\BaseArchive;
use App\Utilities\Strings\StringUtils;

class ZipArchive extends BaseArchive
{
	//
	// zip specific archive methods
	//

	function getFileTypes($dirname) {

		// open zip archive
		//
		$zipArchive = new \ZipArchive();
		$zipArchive->open($this->path);

		// get root directory name
		//
		if ($dirname == '.' || $dirname == './') {
			$dirname = '';
		}

		// get file info array from zip archive
		//
		$fileTypes = [];
		for ($i = 0; $i < $zipArchive->numFiles; $i++) {
			$stat = $zipArchive->statIndex($i);
			$name = $stat['name'];

			if (!$this->isDirectoryName($name)) {
				$info = [
					'name' => $name
				];

				if ($dirname == null) {

					// all files and directories
					//
					$extension = pathinfo($name, PATHINFO_EXTENSION);
					if (array_key_exists($extension, $fileTypes)) {
						$fileTypes[$extension]++;
					} else {
						$fileTypes[$extension] = 1;
					}
				} else if (StringUtils::startsWith($name, $dirname)) {

					// found file in target directory
					//
					$extension = pathinfo($name, PATHINFO_EXTENSION);
					if (array_key_exists($extension, $fileTypes)) {
						$fileTypes[$extension]++;
					} else {
						$fileTypes[$extension] = 1;
					}
				}
			}
		}
		
		return $fileTypes;
	}
	
	function getArchiveFilenames($zipArchive) {
		$names = [];
		for ($i = 0; $i < $zipArchive->numFiles; $i++) {
			$stat = $zipArchive->statIndex($i);
			$name = $stat['name'];

			// make sure that directory name for path exists
			//
			$dirname = dirname($name);
			if ($dirname && $dirname != '.' && $dirname != '/') {
				$this->addPath($dirname.'/', $names);
			}

			// add name
			//
			array_push($names, $name);
		}
		return $names;
	}

	function getFileInfoList($dirname = null, $filter = null, $recursive = false) {

		// check for root directory
		//
		if ($dirname == '.' ||  $dirname == './') {
			$dirname = null;
		}

		// open zip archive
		//
		$zipArchive = new \ZipArchive();
		$zipArchive->open($this->path);

		// get file names from archive
		//
		$names = $this->getArchiveFilenames($zipArchive);

		// get root directory name
		//
		if (!$dirname) {
			$root = $this->getRootDirectoryName($names);
		} else {
			$root = $dirname;
		}

		// filter for directory names
		//
		if ($dirname || !$recursive) {
			$names = $this->getNamesInDirectory($names, $root, $recursive);
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

	function getDirectoryInfoList($dirname = null, $filter = null, $recursive = false) {

		// open zip archive
		//
		$zipArchive = new \ZipArchive();
		$zipArchive->open($this->path);

		// get root directory name
		//
		if ($dirname == '.' || $dirname == './') {
			$dirname = '';
		}

		// get directory info array from zip archive
		//
		$directories = [];
		for ($i = 0; $i < $zipArchive->numFiles; $i++) {
			$stat = $zipArchive->statIndex($i);
			$name = $stat['name'];

			if ($filter == null || $filter == basename($name)) {
				if ($this->isDirectoryName($name)) {
					if ($dirname == null) {

						// all files and directories
						//
						array_push($directories, $stat);
					} else if ($recursive) {
						if (StringUtils::startsWith(dirname($name).'/', $dirname)) {

							// found file in target path
							//
							array_push($directories, $stat);
						}
					} else {
						if (dirname($name).'/' == $dirname) {

							// found file in target directory
							//
							array_push($directories, $stat);
						}
					}
				}
			}
		}

		return $directories;
	}

	public function extractTo($destination, $filenames = null) {
		$zipArchive = new \ZipArchive();
		$zipArchive->open($this->path);

		// remove destination file / directory if already exists
		//
		if (file_exists($destination)) {
			$this->rmdir($destination);
		}

		$zipArchive->extractTo($destination, $filenames);
	}

	public function extractContents($filePath) {

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
