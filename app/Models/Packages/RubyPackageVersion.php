<?php
/******************************************************************************\
|                                                                              |
|                            RubyPackageVersion.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of type of package version.                      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class RubyPackageVersion extends PackageVersion
{
	//
	// attributes
	//

	const BUILD_FILES = ['Gemfile', 'Rakefile'];
	const SOURCE_FILES = '/\.(rb)$/';

	//
	// querying methods
	//

	function getGemInfo($dirname) {
		$contents = self::getFileContents('Gemfile', $dirname);

		if ($contents) {

			// parse file
			//
			return self::parseGemInfo(explode("\n", $contents));
		} else {
			return response("Could not get Gemfile contents.", 404);
		}
	}

	function getGemType() {
		$contents = file_get_contents($this->getPackagePath());

		// check for framework dependencies
		//
		if (strpos($contents, "gem 'sinatra'") !== false) {
			return 'sinatra';
		} else if (strpos($contents, "gem 'rails'") !== false) {
			return 'rails';
		} else if (strpos($contents, "gem 'padrino'") !== false) {
			return 'padrino';

		// no framework dependencies found
		//
		} else {
			return 'ruby';
		}
	}

	function getBuildSystem() {

		// check file extension of archive file
		//
		$extension = pathinfo($this->getPackagePath(), PATHINFO_EXTENSION);
		if ($extension == 'gem') {
			return 'ruby-gem';
		} else {

			// search archive for build files
			//
			$archive = Archive::create($this->getPackagePath());
			$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);
			$foundGemfile = $archive->found($searchPath, 'Gemfile');
			$foundRakefile = $archive->found($searchPath, 'Rakefile');

			// deduce build system from build files
			//
			if ($foundGemfile && $foundRakefile) {
				return 'bundler+rake'; 
			} else if ($foundGemfile) {
				return 'bundler';
			} else if ($foundRakefile) {
				return 'rake';
			} else {
				return none;
			}
		}
	}

	function getBuildInfo() {

		// initialize build info
		//
		$buildSystem = null;
		$configDir = null;
		$configCmd = null;
		$buildFile = null;
		$buildDir = null;
		$buildCmd = null;
		$noBuildCmd = null;
		$sourceFiles = [];

		$extension = pathinfo($this->getPackagePath(), PATHINFO_EXTENSION);
		if ($extension == 'gem') {
			$buildSystem = 'ruby-gem';
		} else {

			// search archive for build files
			//
			$archive = Archive::create($this->getPackagePath());
			$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

			// find build file paths
			//
			$gemPath = $archive->search($searchPath, ['Gemfile', 'gemfile']);
			$rakePath = $archive->search($searchPath, ['Rakefile', 'rakefile']);

			// find path to source files for no build
			//
			$sourcePath = $searchPath;
			/*
			$sourceFilePath = $archive->find($searchPath, self::SOURCE_FILES, true);
			$sourcePath = dirname($sourceFilePath) . '/';
			*/

			// strip off leading source path
			//
			if (StringUtils::startsWith($gemPath, $this->source_path)) {
				$gemPath = substr($gemPath, strlen($this->source_path));
			}
			if (StringUtils::startsWith($rakePath, $this->source_path)) {
				$rakePath = substr($rakePath, strlen($this->source_path));
			}

			// deduce build system from build files
			//
			if ($gemPath && $rakePath) {
				$buildSystem = 'bundler+rake';
				$buildDir = dirname($rakePath);
				if ($buildDir == '.') {
					$buildDir = null;
				}
			} else if ($gemPath) {
				$buildSystem = 'bundler';
				$buildDir = dirname($gemPath);
				if ($buildDir == '.') {
					$buildDir = null;
				}
			} else if ($rakePath) {
				$buildSystem = 'rake';
				$buildDir = dirname($rakePath);
				if ($buildDir == '.') {
					$buildDir = null;
				}
			} else {
				$buildSystem = null;
			}

			// find and sort source files
			//
			$sourceFiles = $archive->getListing($sourcePath, self::SOURCE_FILES, true);
			sort($sourceFiles);

			// compose no build command
			//
			if ($sourceFiles && count($sourceFiles > 0)) {

				// add cd to build directory
				//
				if ($buildDir && $buildDir != '.' && $buildDir != './') {
					$noBuildCmd = 'cd ' .  $archive->toPathName($buildDir) . ';';
				}
			}
		}

		return [
			'build_system' => $buildSystem,
			'config_dir' => $configDir,
			'config_cmd' => $configCmd,
			'build_dir' => $buildDir,
			'build_cmd' => $buildCmd,
			'no_build_cmd' => $noBuildCmd,
			'source_files' => $sourceFiles
		];
	}

	function checkBuildSystem() {
		switch ($this->build_system) {

			case 'bundler+rake':

				// search archive for Gemfile
				//
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				if (!$archive->contains($searchPath, 'Gemfile')) {
					return response("Could not find a Gemfile within the '" . $searchPath . "' directory.  You may need to set your build path or the path to your build file.", 404);
				}

				// search archive for build file
				//
				if ($this->build_file != null) {

					// check for specified build file
					//
					if ($archive->contains($searchPath, $this->build_file)) {
						return response("Ruby package build system ok for bundler+rake.", 200);
					} else {
						return response("Could not find a build file called '" . $this->build_file . "' within the '" . $searchPath . "' directory.  You may need to set your build path or the path to your build file.", 404);
					}
				} else {

					// search archive for default build file
					//
					if ($archive->contains($searchPath, 'rakefile') || 
						$archive->contains($searchPath, 'Rakefile')
					) {
						return response("Ruby package build system ok for bundler+rake.", 200);
					} else {
						return response("Could not find a build file called 'rakefile' or 'Rakefile' within '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
					}
				}
				break;

			case 'bundler':
			case 'bundler+other':

				// search archive for Gemfile
				//
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				if ($archive->contains($searchPath, 'Gemfile')) {
					return response("Ruby package build system ok for bundler+other.", 200);
				} else {
					return response("Could not find a Gemfile within the '" . $searchPath . "' directory.  You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'rake':

				// search archive for specified build file
				//
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				if ($this->build_file != null) {

					// check for specified build file
					//
					if ($archive->contains($searchPath, $this->build_file)) {
						return response("Ruby package build system ok for rake.", 200);
					} else {
						return response("Could not find a build file called '" . $this->build_file . "' within the '" . $searchPath . "' directory.  You may need to set your build path or the path to your build file.", 404);
					}
				} else {

					// search archive for default build file
					//
					if ($archive->contains($searchPath, 'rakefile') || 
						$archive->contains($searchPath, 'Rakefile')
					) {
						return response("Ruby package build system ok for rake.", 200);
					} else {
						return response("Could not find a build file called 'rakefile' or 'Rakefile' within '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
					}		
				}
				break;

			case 'ruby-gem':
				$path_parts = pathinfo($this->getPackagePath());
				if ($path_parts['extension'] == 'gem') {
					return response("Ruby package build system ok for ruby-gem.", 200);
				} else {
					return response("The package archive file extension should be '.gem' for the ruby-gem build system.", 404);
				}
				break;

			case 'no-build':
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				// check for source files
				//
				$sourceFiles = $archive->getListing($searchPath, self::SOURCE_FILES, true);
				if (count($sourceFiles) > 0) {
					return response("Ruby package build system ok for no-build.", 200);
				} else {
					return response("No assessable Ruby code files were found in the selected build path ($searchPath).", 404);
				}
				break;

			default:
				return response("Ruby package build system ok for " . $this->build_system . ".", 200);
		}
	}

	//
	// utility methods
	//

	private static function parseGemItem($string) {
		$string = trim($string);

		// single quotated string literals
		//
		if ($string[0] == "'") {
			return (object)[
				'strlit' => trim($string, "'")
			];

		// double quotated string literals
		//
		} else if ($string[0] == '"') {
			return (object)[
				'strlit2' => trim($string, '"')
			];

		// symbols
		//
		} else if ($string[0] == ':') {
			$string = ltrim($string, ':');
			$string = rtrim($string);

			// symbol definition
			//
			if (strpos($string, '=>') != false) {
				$items = explode('=>', $string);
				return [
					'symbol' => trim($items[0]),
					'value' => self::parseGemItem(trim($items[1]))
				];		

			// symbol instance
			//
			} else {
				return [
					'symbol' => trim($string)
				];
			}

		// requires
		//
		} else if (substr($string, 0, 8) == 'require:') {
			$items = explode(':', $string);
			return [
				'require' => trim($items[1], " ,\'\"\t\n\r\0\x0B")
			];		
		} else {

			// variables
			//
			return $string;
		}
	}

	private static function parseGemItems($string) {
		$words = explode(', ', $string);
		$items = [];
		for ($count = 0; $count < sizeof($words); $count++) {
			array_push($items, self::parseGemItem(trim($words[$count], " ,\t\n\r\0\x0B")));
		}
		return $items;
	}

	private static function parseGemInfo($lines) {
		$array = [];
		$numLines = sizeof($lines);
		$currentLine = 0;

		while ($currentLine < $numLines) {

			// go to next line
			//
			$line = $lines[$currentLine++];
			if ($line != '') {

				// parse line
				//
				if ($line[0] == "#") {
					$line = ltrim($line, '#');

					// parse comment
					//
					array_push($array, [
						'comment' => trim($line)
					]);
				} else {

					// parse line
					//
					$words = explode(' ', $line);

					// check for source
					//
					if ($words[0] == 'source') {
						if ($words[1]) {
							array_push($array, [
								'source' => self::parseGemItem(ltrim($line, 'source'))
							]);
						}

					// check for ruby version
					//
					} else if ($words[0] == 'ruby') {
						if ($words[1]) {
							array_push($array, [
								'ruby' => self::parseGemItem(ltrim($line, 'ruby'))
							]);
						}

					// check for gems
					//
					} else if ($words[0] == 'gem') {

						// parse list of gem values
						//
						array_push($array, [
							'gem' => self::parseGemItems(ltrim($line, 'gem'))
						]);

					// check for groups
					//
					} else if ($words[0] == 'group') {

						// parse groups
						//
						if ($words[1]) {

							// parse group names
							//
							$line = trim($line);
							$line = ltrim($line, "group");
							$line = rtrim($line, "do");
							$groups = self::parseGemItems($line);
							$gems = [];

							// go to next line
							//
							$line = trim($lines[$currentLine++]);

							while ($line != 'end') {
								$words = explode(' ', $line);

								// check for gems
								//
								if ($words[0] == 'gem') {

									// parse list of gem values
									//
									array_push($gems, [
										'gem' => self::parseGemItems(trim($line, 'gem'))
									]);
								}

								// go to next line
								//
								$line = trim($lines[$currentLine++]);
							}

							// add new group
							//
							array_push($array, [
								'group' => $groups,
								'gems' => $gems
							]);

							// go to next line
							//
							if ($currentLine < $numLines) {
								$line = trim($lines[$currentLine++]);
							}
						}
					} else {

						// other code
						//
						array_push($array, $line);
					}
				} 
			}
		}

		return $array;
	}
}
