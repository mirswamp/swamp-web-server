<?php
/******************************************************************************\
|                                                                              |
|                                BaseArchive.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines an abstract base class for handling archive files.       |
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

use App\Utilities\Strings\StringUtils;

class BaseArchive
{
	//
	// private attributes
	//

	public $path;
	
	//
	// constructor
	//

	public function __construct(?string $path = null) {
		$this->path = $path;
	}

	//
	// static path related utility methods
	//

	function toPath(?string $str): ?string {
		if ($str && $str != '') {
			if ($str == '.') {
				return '';
			} else if (!StringUtils::endsWith($str, '/')) {
				return $str . '/';
			}
		}
		return $str;
	}

	function normalizePaths(string &$path, string &$file) {

		// normalize dot path
		//
		if ($path == '.' || $path == './') {
			$path = '';
		}

		// back out of path if filename starts with ../
		//
		while (StringUtils::startsWith($file, '../')) {

			// strip leading ../
			//
			$file = substr($file, 3);

			// strip trailing slash
			//
			$path = substr($path, 0, strlen($path) - 1);

			// back up one level in path
			//
			if (strpos($path, '/') != false) {

				// strip from end until next slash
				//
				$path = substr($path, 0, strrpos($path, '/') + 1);
			} else {

				// path is empty
				//
				$path = '';
			}
		}

		// move directory names from file to path
		//
		$slashPosition = strpos($file, '/');
		if ($slashPosition) {
			$filepath = substr($file, 0, $slashPosition + 1);
			$filename = substr($file, $slashPosition + 1);
			$path = $path.$filepath;
			$file = $filename;
		}
	}

	function concatPaths(?string $path1, ?string $path2): string {

		// check if both paths exist
		//
		if (!$path1 || $path1 == '') {
			$path = $path2;
		} else if (!$path2 || $path2 == '') {
			$path = $path1;
		} else {

			// concatenate paths
			//
			$this->normalizePaths($path1, $path2);
			$path = $this->toPath($path1) . $this->toPath($path2);
		}

		if ($path == '') {
			$path = '.';
		}

		return $path;
	}

	function toPathName(?string $path): ?string {

		// strip trailing slash
		//	
		if (StringUtils::endsWith($path, '/')) {
			$path = substr($path, 0, strlen($path) - 1);
		}

		// add quotation marks if necessary
		//
		if (StringUtils::contains($path, ' ')) {
			$path = '"' . $path . '"';
		}

		return $path;
	}

	//
	// public methods
	//

	public function getRoot(bool $trim = true, bool $pad = true): ?string {
		$list = $this->getFileInfoList(null, null, false, $trim);
		$names = $this->infoListToNames($list, $trim);
		return $this->getRootDirectoryName($names, $trim, $pad);
	}

	public function getExtension(): ?string {
		return pathinfo($this->path, PATHINFO_EXTENSION);
	}

	public function find(?string $path, string $filter = null, bool $recursive = false): ?string {
		$info = $this->getFileInfoList($path, $filter, $recursive);
		$names = $this->infoArrayToNames($info);

		if ($names) {
			$name = $names[0];

			// strip leading ./ from name
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			return $name;
		}

		return null;
	}

	public function getListing(?string $path, string $filter = null, bool $recursive = false): array {
		$info = $this->getFileInfoList($path, $filter, $recursive);
		$names = $this->infoArrayToNames($info);

		// strip leading ./ from names
		//
		for ($i = 0; $i < sizeof($names); $i++) {
			if (StringUtils::startsWith($names[$i], './')) {
				$names[$i] = substr($names[$i], 2);
			}
		}

		return $names;
	}

	public function contains(?string $dirname, string $filename): bool {
		$this->normalizePaths($dirname, $filename);

		if (StringUtils::startsWith($dirname, './')) {
			$dirname = substr($names[$i], 2);
		}

		// look in top level of specified directory
		//
		if ($dirname && $dirname != '.') {
			$path = $dirname.$filename;
		} else {
			$path = $filename;
		}

		return in_array($path, $this->getListing($dirname));
	}

	public function search(?string $dirname, array $filenames, bool $recursive = true): ?string {

		foreach ($filenames as $filename) {
			$this->normalizePaths($dirname, $filename);
		}

		$info = $this->getFileInfoList($dirname, null, $recursive);
		$names = $this->infoArrayToNames($info);

		// strip leading ./ from names
		//
		for ($i = 0; $i < sizeof($names); $i++) {
			if (StringUtils::startsWith($names[$i], './')) {
				$names[$i] = substr($names[$i], 2);
			}
		}

		// sort names by nesting level, then alphabetically
		//
		usort($names, function($a, $b) {
			$aNesting = substr_count($a, '/');
			$bNesting = substr_count($b, '/');
			if ($aNesting != $bNesting) {
				return $aNesting > $bNesting;
			} else {
				return $a > $b;
			}
		});

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			for ($j = 0; $j < sizeof($filenames); $j++) {
				if (basename($name) == $filenames[$j]) {
					return $name;
				}
			}
		}

		return null;
	}

	public function found(?string $dirname, ?string $filter, bool $recursive = false): bool {
		$this->normalizePaths($dirname, $filter);
		return sizeof($this->getFileInfoList($dirname, $filter, $recursive)) != 0;
	}

	public function getFileInfoTree(?string $dirname, ?string $filter): array {
		$list = $this->getFileInfoList($dirname, $filter);
		return $this->directoryInfoListToTree($list);
	}

	public function getDirectoryInfoTree(?string $dirname, ?string $filter): array {
		$list = $this->getDirectoryInfoList($dirname, $filter);
		return $this->directoryInfoListToTree($list);
	}

	//
	// protected directory utility methods
	//

	public function getRootDirectoryName(array $array, bool $trim = true, bool $pad = true): string {

		if (sizeof($array) == 0) {
			if (!$pad) {
				return '';
			} else {
				return './';
			}
		}

		// take the first item as initial prefix
		//
		$prefix = $array[0];
		$length = strlen($prefix);

		// find the common prefix
		//
		foreach ($array as $name) {

			// strip leading ./
			//
			if ($trim) {
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}
			}

			// check if there is a match; if not, decrease the prefix length by one
			//
			while ($length > 0 && substr($name, 0, $length) !== $prefix) {
				$length--;
				$prefix = substr($prefix, 0, $length);
			}
		}

		// find directory name of common prefix
		//
		$last = strpos($prefix, '/');
		if ($last != false) {
			$prefix = substr($prefix, 0, $last + 1);
		} else {
			$prefix = null;
		}

		if (!$prefix) {
			if (!$pad) {
				$prefix = '';
			} else {
				$prefix = './';
			}
		}

		return $prefix;
	}

	protected function isDirectoryName(?string $path): bool {
		return $path[strlen($path) - 1] == '/';
	}

	protected function addName(string $name, array &$dirnames) {
		if (!in_array($name, $dirnames)) {

			// add directory of name
			//
			$dirname = dirname($name);
			if ($dirname != '.' && $dirname != './') {
				$this->addName($dirname.'/', $dirnames);
			}

			// add name
			//
			array_push($dirnames, $name);
		}
	}

	protected function getFileAndDirectoryNames(array $names, bool $trim = true): array {
		$dirnames = [];

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];

			// strip leading ./
			//
			if ($trim && StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			if ($name && !in_array($name, $dirnames)) {

				// add directory of name
				//
				$dirname = dirname($name);
				if ($dirname != '.') {
					$this->addName($dirname . '/', $dirnames);
				}

				// add file or directory
				//
				array_push($dirnames, $name);
			}
		}

		return $dirnames;
	}

	//
	// protected file and directory name filtering methods
	//

	protected function getDirectoryNames(array $names): array {
		$directoryNames = [];

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if ($this->isDirectoryName($name)) {

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($directoryNames, $name);
			}
		}

		return $directoryNames;
	}

	protected function getFileNames(array $names): array {
		$fileNames = [];

		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			if (!$this->isDirectoryName($name)) {

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($fileNames, $name);
			}
		}

		return $fileNames;
	}

	//
	// protected name / directory filtering methods
	//

	protected function getNamesInDirectory(array $names, string $dirname = null, bool $recursive = false, bool $trim = true): array {
		$dirnames = [];

		// make sure that dirname ends with a slash
		//
		if (!StringUtils::endsWith($dirname, '/')) {
			$dirname = $dirname.'/';
		}

		if (!$dirname) {
			if ($recursive) {

				// return all
				//
				for ($i = 0; $i < sizeof($names); $i++) {
					$name = $names[$i];

					// strip leading ./
					//
					if (StringUtils::startsWith($name, './')) {
						$name = substr($name, 2);
					}

					array_push($dirnames, $name);
				}
				return $dirnames;
			} else {

				// return top level items
				//
				for ($i = 0; $i < sizeof($names); $i++) {
					$name = $names[$i];

					// strip leading ./
					//
					if (StringUtils::startsWith($name, './')) {
						$name = substr($name, 2);
					}

					if (!StringUtils::contains($name, '/')) {
						array_push($dirnames, $name);
					}
				}
				return $dirnames;
			}
		}

		// get names that are part of target directory
		//
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];

			// strip leading ./
			//
			if ($trim) {
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}
			}

			if ($recursive) {
				if (StringUtils::startsWith(dirname($name).'/', $dirname)) {
					array_push($dirnames, $name);
				}
			} else {
				if (dirname($name).'/' == $dirname) {
					array_push($dirnames, $name);
				}
			}
		}

		return $dirnames;
	}

	protected function getNamesNestedInDirectory(array $names, string $dirname = null): array {

		// return all if no dirname
		//
		if (!$dirname) {
			$dirnames = [];
			for ($i = 0; $i < sizeof($names); $i++) {
				$name = $names[$i];

				// strip leading ./
				//
				if (StringUtils::startsWith($name, './')) {
					$name = substr($name, 2);
				}

				array_push($dirnames, $name);
			}
			return $dirnames;
		}

		// get names that are part of target directory (nested)
		//
		$dirnames = [];
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			if (StringUtils::startsWith($name, $dirname)) {
				array_push($dirnames, $name);
			}
		}

		return $dirnames;
	}

	protected function getFilteredNames(array $names, string $filter): array {

		// return all if no filter
		//
		if (!$filter) {
			return $names;
		}

		// get names that are inside of target directory
		//
		$filteredNames = [];
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = basename($names[$i]);

			if (StringUtils::startsWith($filter, '/')) {

				// use regular expression
				//
				if (preg_match($filter, $name)) {
					array_push($filteredNames, $names[$i]);
				}			
			} else {

				// use string match
				//
				if ($filter == $name || $filter == basename($name)) {
					array_push($filteredNames, $names[$i]);
				}				
			}
		}

		return $filteredNames;
	}

	//
	// protected file name / info conversion methods
	//

	protected function nameToInfo(string $name): array {
		return [
			'name' => $name
		];
	}

	protected function namesToInfoArray(array $names): array {
		$info = [];
		foreach ($names as $name) {
			array_push($info, $this->nameToInfo($name));
		}
		return $info;
	}

	protected function infoArrayToNames(array $info): array {
		$names = [];
		foreach ($info as $item) {
			array_push($names, $item['name']);
		}
		return $names;
	}

	//
	// protected file type inspection methods
	//

	protected function getFileTypesFromNames(array $names): array {
		$fileTypes = [];
		for ($i = 0; $i < sizeof($names); $i++) {
			$name = $names[$i];
			$extension = pathinfo($name, PATHINFO_EXTENSION);
			if (array_key_exists($extension, $fileTypes)) {
				$fileTypes[$extension]++;
			} else {
				$fileTypes[$extension] = 1;
			}
		}
		return $fileTypes;
	}

	//
	// protected archive methods
	//

	protected function addPath(string $path, array &$names) {
		if ($path && $path != '.' && $path != '/' && !in_array($path, $names)) {

			// add parent dirname
			//
			$dirname = dirname($path);
			if ($dirname && $dirname != '.' && $dirname != '/') {
				$this->addPath($dirname.'/', $names);
			}

			// add path
			//
			array_push($names, $path);
		}
	}

	protected function containsDirectoryNames(string $names): bool {
		foreach ($names as $value) {
			if (StringUtils::endsWith($value, '/')) {
				return true;
			}
		}
		return false;
	}

	protected function inferDirectoryNames(array $names): array {
		foreach ($names as $value) {

			// for each file name
			//
			if (!StringUtils::endsWith($value, '/')) {
				$terms = explode('/', $value);
				if (sizeof($terms) > 1) {

					// iterate over directory names
					//
					$path = '';
					foreach (array_slice($terms, 0, -1) as $directory) {

						// compose path
						//
						$path .= $directory.'/'; 

						// add new paths to list
						//
						if (!in_array($path, $names)) {
							array_push($names, $path);
						}
					}
				}
			}
		}

		// sort names so that new paths are not at the end
		//
		sort($names);

		return $names;
	}

	//
	// protected directory list to tree conversion methods
	//

	public function infoListToNames(array $list, bool $trim = true): array {
		$names = [];
		for ($i = 0; $i < sizeof($list); $i++) {
			$name = $list[$i]['name'];

			// strip leading ./
			//
			if ($trim && StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			array_push($names, $name);
		}

		return $names;
	}

	protected function directoryInfoListToTree(array $list): array {
		$tree = [];

		function &findLeaf(&$tree, $name) {

			// strip leading ./
			//
			if (StringUtils::startsWith($name, './')) {
				$name = substr($name, 2);
			}

			for ($i = 0; $i < sizeof($tree); $i++) {
				$treeName = $tree[$i]['name'];

				// strip leading ./
				//
				if (StringUtils::startsWith($treeName, './')) {
					$treeName = substr($treeName, 2);
				}

				if ($treeName == $name) {
					return $tree[$i];
				} else if (array_key_exists('contents', $tree[$i])) {
					$leaf = &findLeaf($tree[$i]['contents'], $name);
					if ($leaf != null) {
						return $leaf;
					}
				}
			}

			$leaf = null;
			return $leaf;
		}

		for ($i = 0; $i < sizeof($list); $i++) {
			$item = $list[$i];
			$dirname = dirname($item['name']);

			// strip leading ./
			//
			if (StringUtils::startsWith($dirname, './')) {
				$dirname = substr($dirname, 2);
			}

			// search for item in tree
			//
			$leaf = &findLeaf($tree, $dirname != '/'? $dirname.'/' : $dirname);

			if ($leaf != null) {

				// create directory contents
				//
				if (!array_key_exists('contents', $leaf)) {
					$leaf['contents'] = [];
				}

				// add item to leaf
				//
				array_push($leaf['contents'], $item);
			} else {

				// add item to root of tree
				//
				array_push($tree, $item);
			}
		}

		if (!array_key_exists('contents', $tree)) {

			// find root name
			//
			$names = $this->infoListToNames($tree);
			if (sizeof($names) > 0) {
				$name = $this->getRootDirectoryName($names);
				if ($name == '') {
					$name = './';
				}
			} else {
				$name = './';
			}

			$tree = [
				'name' => $name,
				'contents' => $tree
			];
		}

		return $tree;
	}

	public static function rmdir(string $dir) { 
		if (is_dir($dir)) { 
			$objects = scandir($dir); 
			foreach ($objects as $object) { 
				if ($object != '.' && $object != '..') { 
					if (is_dir($dir . '/' . $object)) {
						self::rmdir($dir . '/' . $object);
					} else {
						unlink($dir . '/' . $object); 
					}
				} 
			}
			rmdir($dir); 
		} 
	}
}
