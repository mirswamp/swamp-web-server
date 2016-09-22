<?php
/******************************************************************************\
|                                                                              |
|                                  Country.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of the telephone information asociated           |
|        with a particular country.                                            |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Utilities;

use App\Models\BaseModel;

class Country extends BaseModel {

	/**
	 * database attributes
	 */
	public $primaryKey = 'country_id';
	protected $table = 'countries';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'name',
		'iso',
		'iso3',
		'num_code',
		'phone_code'
	);

	/**
	 * constructor
	 */
	public function __construct(array $attributes = array()) {
		parent::__construct($attributes);

		// override properties set by base model
		//
		$this->timestamps = false;
	}
}
