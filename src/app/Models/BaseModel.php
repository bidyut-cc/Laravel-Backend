<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Crud;

class BaseModel extends Model
{
	use SoftDeletes, Crud;
}
