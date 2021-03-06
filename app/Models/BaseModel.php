<?php
/******************************************************************************\
|                                                                              |
|                                  BaseModel.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines an abstract base model class to extend.                  |
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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

abstract class BaseModel extends Model
{
	// attributes
	//
	public $timestamps = false;

	//
	// querying methods
	//

	public function isNew(): bool {
		return $this[$this->primaryKey] == null;
	}
	
	public function isSameAs($object): bool {
		return $object && $this[$this->getKeyName()] == $object[$object->getKeyName()];
	}

	//
	// model updating methods
	//

	public function change(array $attributes = []) {
		$this->fill($attributes);
		$changes = $this->getDirty();
		$this->save();
		return $changes;
	}

	//
	// attribute visibility methods
	//

	public function getVisible(): array {

		// compose list of visible items hierarchically
		//
		$parentClass = get_parent_class($this);
		if ($parentClass != get_class()) {

			// subclasses
			//
			return array_merge((new $parentClass)->getVisible(), $this->visible);
		} else {
			return $this->visible;
		}
	}

	protected function getArrayableItems(array $values) {
		$visible = $this->getVisible();
		$className = get_class($this);

		if (count($visible) > 0) {
			return array_intersect_key($values, array_flip($visible));
		}

		return array_diff_key($values, array_flip($this->hidden));
	}
}
