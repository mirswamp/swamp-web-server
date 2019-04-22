<?php
/******************************************************************************\
|                                                                              |
|                              CookieCleaner.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware for removing excess cookies.                  |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class CookieCleaner
{
	/**
	 * Handle an incoming request.
 	 * Check for orphaned 40-character hexadecimal cookies. The 40-char hex
	 * cookies correspond to the 'named' session cookie (e.g.,
	 * 'laravel_session') and contain the actual data for the session. Since
	 * the JavaScript can call several routes asynchronously, it is possible
	 * that more than one route would try to set session data (in the hex
	 * cookie), but only one hex cookie can be referenced by the 'named' 
	 * cookie. This can result in orphaned 40-char hex cookies which need 
	 * to be cleaned out.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next) {
		$hexacookies = [];
		$laracookies = [];
		$cookies = Cookie::get(); 	// Fetch all cookies

		// Loop through all cookies, separating them into two sets:
		// (1) Cookies with a 40-charachter hexadecimal NAME. These are Laravel
		//     session id cookies created automatically when a variable is saved
		//     to the session.
		// (2) Cookies with a 40-charachter hexadecimal VALUE. These are the
		//     "named" Laravel session cookies (e.g., swamp_csa_session) which act
		//     as a pointer to the session id cookies (1).
		foreach ($cookies as $name => $value) {
			if (is_string($name) && preg_match('/^[a-f0-9]{40}$/',$name)) {
				$hexacookies[$name] = 1;
			} elseif (is_string($value) && preg_match('/^[a-f0-9]{40}$/',$value)) {
				$laracookies[$value] = 1;
			}
		}

		// Now loop through the 'lara' cookies. If one matches a hexadecimal
		// cookie, remove it from the $hexacookies array since that is a valid 
		// Laravel session id cookie as indexed by the "named" session cookie.
		//
		foreach ($laracookies as $name => $value) {
			if (array_key_exists($name,$hexacookies)) {
				unset($hexacookies[$name]);
			}
		}

		// Finally, anything left in the $hexacookies array is an orphaned
		// session id cookie and should be deleted.
		//
		if (count($hexacookies) > 0) {
			$confpath = config('session.path') || '/';
			$confdomain = config('session.domain') || '';
			foreach ($hexacookies as $name => $value) {
				Log::notice("Deleting orphaned cookie.", [
					'cookie_name' => $name
				]);
				$domain = $confdomain;
				$domainvalid = true;

				// Delete the cookie for all possible combinations of domain
				// sub-parts and paths. Secure setting is not important when
				// deleting a cookie.
				//
				while ($domainvalid) {
					foreach (['', $confpath] as $path) {

						// Must use PHP's setcookie() to delete all subdomain cookies
						//
						setcookie($name,'',1,$path,$domain);
					}

					// The following mess strips off strings from the front of the
					// domain to loop through all sub-components. For example, if
					// domain is a.b.org, we want to loop through the following list:
					// a.b.org, .b.org, b.org, '' 
					//
					if (strlen($domain) == 0) {

						// Done looping through domains
						//
						$domainvalid = false;
					} elseif (substr_count($domain,'.') > 1) {

						// At least 2 dots
						//
						if (substr($domain,0,1) == '.') {

							// Strip off leading dot
							//
							$domain = substr($domain,1);
						} else { 

							// Strip off leading non-dot string ('a')
							//
							$domain = strstr($domain,'.');
						}
					} else {

						// Only 1 dot left (b.org), set domain to default
						//
						$domain = '';
					}
				}
			}
		}

		return $next($request);
	}
}