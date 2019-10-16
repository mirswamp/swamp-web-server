<?php
/******************************************************************************\
|                                                                              |
|                             ProxyController.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for viewer virtual machine proxies.         |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Viewers;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Viewers\ViewerInstance;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Http\Controllers\BaseController;
use App\Services\HTCondorCollector;
use App\Utilities\Strings\StringUtils;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

class ProxyController extends BaseController
{
	//
	// private data access methods
	//

	private function getViewerData($proxy) {
		$data = [];

		if (config('cache.viewer_proxy_caching') && Cache::has($proxy)) {

			// get viewer data from cache
			//
			$data = Cache::get($proxy);
		} else {

			// get viewer data from HTCondor
			//
			list($vm_ip, $projectUid) = HTCondorCollector::getViewerData($proxy);
			$iterations = 0;
			$maxIterations = 1000;
			while (!$vm_ip && $iterations < $maxIterations) {
				$iterations++;
				usleep(10000);
				list($vm_ip, $projectUid) = HTCondorCollector::getViewerData($proxy);
			}

			$data = [
				'vm_ip' => $vm_ip,
				'project_uid' => $projectUid
			];

			// add viewer data to cache
			//
			if (config('cache.viewer_proxy_caching')) {
				Cache::add($proxy, $data, config('cache.viewer_proxy_caching_duration'));
			}
		}

		return $data;
	}

	private function getUser($userUid) {
		$user = null;

		if (config('cache.viewer_proxy_caching') && Cache::has($userUid)) {
			$user = new User(Cache::get($userUid));
		} else {
			$user = User::getIndex($userUid);

			// add user data to cache
			//
			if (config('cache.viewer_proxy_caching')) {
				Cache::add($userUid, $user->toArray(), config('cache.viewer_proxy_caching_duration'));
			}
		}

		return $user;
	}

	private function getProject($projectUid) {
		$project = null;

		if (config('cache.viewer_proxy_caching') && Cache::has($projectUid)) {
			$project = new Project(Cache::get($projectUid));
		} else {
			$project = Project::where('project_uid', '=', $projectUid)->first();

			// add project data to cache
			//
			if (config('cache.viewer_proxy_caching')) {
				Cache::add($projectUid, $project->toArray(), config('cache.viewer_proxy_caching_duration'));
			}
		}

		return $project;
	}

	//
	// public methods
	//

	public function proxyCodeDxRequest() {

		// find request info
		//
		$method = $_SERVER['REQUEST_METHOD'];
		$requestUri = $_SERVER['REQUEST_URI'];

		// get viewer data
		//
		$proxy = Request::segment(1);
		$data = $this->getViewerData($proxy);
		$vm_ip = $data['vm_ip'];
		$projectUid = $data['project_uid'];

		// check if associated project is valid
		//
		$project = $this->getProject($projectUid);
		if (!$project) {
			return response('No valid project is associated with these results.', 400);
		}

		// check whether current user is a member of this project
		//
		$user = $this->getUser(session('user_uid'));
		if (!$project->isOwnedBy($user) && !$user->isMemberOf($project)) {
			return response('The current user is not a member of this project', 400);
		}
			
		// check cache for response
		//
		if ($method == 'GET' && config('cache.viewer_proxy_caching') && Cache::has($requestUri)) {

			// get cached response
			//
			$data = Cache::get($requestUri);

			// return cached http response
			//
			$headers = $data['headers'];
			$body = $data['body'];
			$status = $data['status'];
			return response($body, $status)
				->withHeaders($headers)
				->header('Transfer-Encoding', '');
		} else {
			if ($vm_ip) {

				// get request data
				//
				$headers = $this->getCodeDxHeaders($user, $vm_ip);
				$content = Request::instance()->getContent();

				// create and send request
				//
				$handler = new CurlHandler();
				$stack = HandlerStack::create($handler);
				$client = new Client([
					'base_uri' => "https://$vm_ip",
					'handler' => $stack
				]);
				$response = $client->request($method, $requestUri, [
					'headers' => $headers,
					'body' => $content,
					'verify' => false,
					'http_errors' => false,
					'decode_content' => false,
					// 'expect' => false
				]);

				if ($response) {

					// decipher guzzle response
					//
					$headers = $response->getHeaders();
					$body = $response->getBody();
					$status = $response->getStatusCode();
					
					// store response data for later use
					//
					if (config('cache.viewer_proxy_caching') && $method == 'GET') {
						$contentType = null;
						
						// find content type
						//
						if (array_key_exists('Content-Type', $headers)) {
							if (is_array($headers['Content-Type'])) {
								$contentType = $headers['Content-Type'][0];
							} else {
								$contentType = $headers['Content-Type'];
							}
						}

						// find if content type is cacheable
						//
						$cacheable = ($contentType == 'text/javascript;charset=utf-8') || ($contentType == 'image/png') || ($contentType == 'image/x-icon') || ($contentType == 'font/woff2');

						// save, if content type is cacheable
						//
						if ($cacheable && !StringUtils::contains($requestUri, 'lift/comet')) {
							Cache::add($requestUri, [
								'headers' => $headers,
								'body' => $body->getContents(),
								'status' => $status
							], config('cache.viewer_proxy_caching_duration'));
						}
					}

					// return http response
					//
					return response($body, $status)
						->withHeaders($headers)
						->header('Transfer-Encoding', '');
				} else {
					Log::warning("Proxy Controller Error - no response to command.");
					return "Error - viewer is no longer available.  Please try again.";
				}
			} else {
				Log::warning("Proxy Controller Error - no viewer instance is available.");
				return "Error - viewer is no longer available.  Please try again.";
			}
		}
	}

	//
	// private methods
	//

	private static function modifyHeaders($headers, $status) {
		$modified = [];
		foreach ($headers as $key => $value) {
			switch (strtolower($key)) {

				// pass along these headers
				//
				case 'content-type':
				case 'set-cookie':
					$modified[$key] = $value;
					break;

				// handle 301 / 302 redirect locations
				//
				case 'location':
					if (in_array($status, ['301', '302'])) {
						$pos = strpos($value[0], '/proxy-');
						$newloc = substr($value[0], $pos);
						$modified[$key] = [$newloc];
					}
					break;
			}
		}
		return $modified;
	}

	private function getCodeDxHeaders($user, $vm_ip) {

		// get request headers
		//
		$headers = getallheaders();

		// set CodeDx session cookie
		//
		if (isset($_COOKIE['JSESSIONID'])) {
			$headers['Cookie'] = 'JSESSIONID=' . $_COOKIE['JSESSIONID']; 
		}

		// set host
		//
		$headers['Host'] = $vm_ip;

		// set CodeDx authorization
		//
		$headers['AUTHORIZATION'] = "SWAMP " . strtolower($user->username);

		// set user agent
		//
		$headers['User-Agent'] = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36";

		// header sometimes required to avoid "Expect: 100-Continue" http errors
		//
		// $headers['Expect'] = '';

		return $headers;
	}
}