<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class BaseController extends Controller
{
	/**
	 * Get the application environment.
	 *
	 * @return string
	 */
	protected function getEnvironment()
	{
		return \App::environment();
	}

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	protected function setupLayout()
	{
		if (!is_null($this->layout)) {
			$this->layout = View::make($this->layout);
		}
	}
}
