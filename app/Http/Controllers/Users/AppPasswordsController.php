<?php
/******************************************************************************\
|                                                                              |
|                        AppPasswordsController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for app passwords. API documentation        |
|        can be found at https://goo.gl/Vqvn84 .                               |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\BaseController;
Use App\Models\Users\AppPassword;
use App\Models\Users\User;
use App\Models\Utilities\Configuration;
use App\Utilities\Strings\AppPasswordString;
use App\Utilities\Uuids\Guid;

class AppPasswordsController extends BaseController {

	/**
	 * Activates when HTTP POST is sent to the app_password route. Creates a
	 * new app password for the currently logged-in user. An optional POST
	 * form field "label" can be set to specify the label to be assigned
	 * to the new app password. Label defaults to empty string.
	 *
	 * Note that this method returns the actual 20-character password to
	 * the user, but the hash of the password is stored in the database.
	 *
	 * @return Response as HTTP code + JSON token of the newly created
	 *         app password.
	 */
	public function postCreate() {
	  $retresponse = null; // Response to be returned.

		// This method works with the currently logged-in user_uid.
		//
		$user_uid = session('user_uid');

		// An optional label can be specified in POST form data, e.g.,
		// 'label=New+label'. If not given, defaults to empty string ''.
		//
		$label = $this->getInputLabel();

		// Check how many app passwords the user currently has, and make sure
		// this number is less than the max number of app passwords available.
		//
		$configuration = new Configuration;
		$app_password_max = $configuration->getAppPasswordMaxAttribute();
		$app_password_count = AppPassword::where('user_uid', $user_uid)->count();
		if ($app_password_count < $app_password_max) { // Okay to create

			// Generate a 20-character password; save its hash to database
			//
			$the_password = AppPasswordString::create();
			$the_password_hash = password_hash($the_password, PASSWORD_BCRYPT);
			$uuid = Guid::create();
			$app_password = new AppPassword([
				'app_password_uuid' => $uuid,
				'user_uid' => $user_uid,
				'password' => $the_password_hash,
				'label' => $label
			]);
			$app_password->save();

			// send email notificaton about new app password creation
			//
			if (config('mail.enabled')) {
				$user = User::getIndex($user_uid);
				if ($user) {
					$user_email = '';

					// Make sure user's email is valid
					//
					if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
						$user_email = $user->email;
					}
					$user_fullname = trim($user->getFullname());
					if (strlen($user_email) > 0) {
						Mail::send('emails.apppassword-created', [
							'url' => config('app.cors_url') ?: '',
							'user' => $user
						], function($message) use ($user_email, $user_fullname) {
							$message->to($user_email, $user_fullname);
							$message->subject('SWAMP App Password Created');
						});
					}
				}
			}

			Log::info("App password created.", [
				'app_password_uuid' => $uuid
			]);

			// When returing the JSON object, return the password instead of the hash
			//
			$app_password_arr = $app_password->toArray();
			$app_password_arr['password'] = $the_password;
			$app_password_arr['create_date'] = $app_password->create_date;
			$retresponse = response()->json($app_password_arr, 200);

		} else {

			// Too many app passwords already, or app passwords are disabled
			//
			$retresponse = response()->json([
				'error' => 'forbidden',
				'error_description' => "Could not create app password due to the maximum number $app_password_max reached."
			], 403);
		}

		return $retresponse;
	}

	/**
	 * Activates when HTTP GET is sent to app_password route. Returns a particular app password.
	 *
	 * @param $appPasswordUuid The app password ID to return. When this is not null, return
	 *        exactly one app password. When null, return ALL app passwords.
	 * @return Response as HTTP code + JSON token of the app password. 
	 */
	public function getIndex($appPasswordUuid) {
		$appPassword = AppPassword::where('app_password_uuid', '=', $appPasswordUuid)->first();

		// check for password not found
		//
		if (!$appPassword) {
			return response('The specified app password identifier could not be found.', 404);
		}

		// check for permission to view password
		//
		$currentUser = User::getIndex(session('user_uid'));
		if ($appPassword->user_uid != $currentUser->user_uid && !$currentUser->isAdmin()) {
			return response('You do not have permission to view this app password.', 401);
		}

		return $appPassword;
	}

	/**
	 * Activates when HTTP GET is sent to app_password route. Returns all app passwords for the current user.
	 *
	 * @return Response as HTTP code + JSON array of the app passwords. 
	 *         If no app passwords are found, an empty JSON arary is
	 *         returned. 
	 */
	public function getAll() {
		return AppPassword::where('user_uid', '=', session('user_uid'))->get();
	}

	/**
	 * Activates when HTTP PUT is sent to app_password route. Allows user to
	 * change the label for a specific app password. The new label is sent
	 * as query parameter, e.g., "?label="New+label". If the "?label=..."
	 * query parameter is absent, the app password label is set to the
	 * empty string ''.
	 *
	 * @param $appPasswordUuid The app password ID to relabel.
	 *
	 * @return Response as HTTP code, with empty body. On error, the error
	 *         description is returned as a JSON token.
	 */
	public function putIndex($appPasswordUuid) {
		$appPassword = AppPassword::where('app_password_uuid', $appPasswordUuid)->first();

		// check for password not found
		//
		if (!$appPassword) {
			return response('The specified app password identifier could not be found.', 404);
		}

		// New label is specified by query parameter, e.g., '?label="new+label"'.
		// If not given, default to empty string ''.
		//
		$newlabel = $this->getInputLabel();

		// check for permission to change password
		//
		$currentUser = User::getIndex(session('user_uid'));
		if ($appPassword->user_uid != $currentUser->user_uid && !$currentUser->isAdmin()) {
			return response('You do not have permission to change this app password.', 401);
		}

		$appPassword->label = $newlabel;
		$appPassword->save();

		return $appPassword;
	}

	/**
	 * Activates when HTTP DELETE is sent to app_password route. If an app
	 * password ID is specified in the URL, then delete a single app password
	 * for a given user. Otherwise, delete ALL app passwords for a given user.
	 *
	 * @param $appPasswordUuid The app password ID to delete. When this is not null,
	 *        delete exactly one app password. When null, delete ALL app passwords.
	 * @return Response as HTTP code, with empty body. On error, the error
	 *         description is returned as a JSON token.
	 */
	public function deleteIndex($appPasswordUuid) {
		$appPassword = AppPassword::where('app_password_uuid', $appPasswordUuid)->first();

		if ($appPassword) {

			// check for permission to delete password
			//
			$currentUser = User::getIndex(session('user_uid'));
			if ($appPassword->user_uid != $currentUser->user_uid && !$currentUser->isAdmin()) {
				return response('You do not have permission to delete this app password.', 401);
			}

			// Delete just one specific app password
			//
			$appPassword->delete();

			// At least one app password was deleted - send email and log event
			//
			if (config('mail.enabled')) {
				$user = User::getIndex($appPassword->user_uid);
				if ($user) {
					$user_email = '';

					// Make sure user's email is valid
					//
					if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
						$user_email = $user->email;
					}
					$user_fullname = trim($user->getFullname());
					if (strlen($user_email) > 0) {
						Mail::send('emails.apppassword-deleted', [
							'url' => config('app.cors_url') ?: '',
							'user' => $user
						], function($message) use ($user_email, $user_fullname) {
							$message->to($user_email, $user_fullname);
							$message->subject('SWAMP App Password Deleted');
						});
					}
				}
			}

		} else {
			return response()->json([
				'error' => 'not_found',
				'error_description' => 'The specified app password identifier could not be found.'
			], 404);
		}

		return $appPassword;
	}

	public function deleteAll() {

		// delete current user's app passwords
		//
		return $this->deleteByUser(session('user_uid'));
	}

	/**
	 * This method is available only to admin users. It calls 'get()' to
	 * return all app passwords for a specific user.

	 * @param $user_uid The user UID to search for in the database.
	 *
	 * @return Response as HTTP code + JSON token of the app password(s). Note
	 *         that when returning ALL app passwords, the resulting JSON
	 *         token is an array, regardless of the number of app passwords.
	 */
	public function getByUser($userUid) {
		return AppPassword::where('user_uid', '=', $userUid)->get();
	}

	/**
	 * This method is available only to admin users. It calls 'delete()' to
	 * delete all app passwords for a specific user.
	 *
	 * @param $userUid The user UID to search for in the database.
	 *
	 * @return Response as HTTP code, with empty body.
	 */
	public function deleteByUser($userUid) {

		// get app passwords for a particular user
		//
		$query = AppPassword::where('user_uid', $userUid);
		$appPasswords = $query->get();

		if (!$appPasswords->isEmpty()) {

			// delete all app passwords for this user
			//
			$query->delete();

			// At least one app password was deleted - send email and log event
			//
			if (config('mail.enabled')) {
				$user = User::getIndex(session('user_uid'));
				if ($user) {
					$user_email = '';

					// Make sure user's email is valid
					//
					if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
						$user_email = $user->email;
					}
					$user_fullname = trim($user->getFullname());
					if (strlen($user_email) > 0) {
						Mail::send('emails.apppassword-deleted', [
							'url' => config('app.cors_url') ?: '',
							'user' => $user
						], function($message) use ($user_email, $user_fullname) {
							$message->to($user_email, $user_fullname);
							$message->subject('SWAMP App Passwords Deleted');
						});
					}
				}
			}
		}

		return $appPasswords;
	}

	/**
	 * A convenience method to look for the "label" Input value, which can be
	 * specified for either postCreate() (as POST form data) or put() (as a
	 * query parameter). If not found, default to empty string ''. Note that
	 * if the "label" value is longer than 63 characters, it is truncated
	 * to 63 characters to fit in the database.
	 *
	 * @return The "label" value given for either postCreate() or put().
	 *         Defaults to empty string '' if not found. Max length of
	 *         63 characters.
	 */
	private function getInputLabel() {

		// parse parameters
		//
		$label = Input::get('label', '');

		// make sure new label isn't too long
		//
		if (strlen($label) > 63) { 
			$label = substr($label, 0, 63);
		}

		return $label;
	}

}
