<?php

namespace App\Models;

use App\Traits\Crud;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Contracts\Permission as PermissionContract;

class Permission extends Model {
	use HasRoles;
	use RefreshesPermissionCache, Crud;

	protected $guarded = ['id'];

	protected static $logAttributes = ['name', 'guard_name'];
	protected $fillable = ['name'];
	public $searchable = ['name'];
	public $defaultSort = 'id';
	public $defaultSortOrder = 'desc';
	public $model = 'permission';
	public $fieldsArray = array(
		'id' => array(
			'field_name' => 'id',
			'db_name' => 'id',
			'type' => 'text',
			'placeholder' => 'Id',
			'listing' => true,
			'show_in_form' => false,
			'sort' => true,
			'required' => false,
			'value' => '',
			'width' => '50'
		),
		'name' => array(
			'field_name' => 'name',
			'db_name' => 'name',
			'type' => 'text',
			'placeholder' => 'Permission',
			'listing' => true,
			'sort' => true,
			'required' => true,
			'value' => '',
			'width' => '50'
		),
		'guard_name' => array(
			'field_name' => 'guard_name',
			'db_name' => 'guard_name',
			'type' => 'text',
			'placeholder' => 'Guard Name',
			'listing' => false,
			'sort' => true,
			'required' => true,
			'value' => '',
			'width' => '50',
			'hidden' => true,
			'show_in_form' => false
		)
	);
	// END //
	public function __construct(array $attributes = []) {
		$attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

		parent::__construct($attributes);

		$this->setTable(config('permission.table_names.permissions'));
	}

	public static function create(array $attributes = []) {
		$attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

		$permission = static::getPermissions(['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']])->first();

		if ($permission) {
			throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
		}

		return static::query()->create($attributes);
	}


	public function roles(): BelongsToMany {
		return $this->belongsToMany(
			config('permission.models.role'),
			config('permission.table_names.role_has_permissions'),
			'permission_id',
			'role_id'
		);
	}


	public function users(): MorphToMany {
		return $this->morphedByMany(
			getModelForGuard($this->attributes['guard_name']),
			'model',
			config('permission.table_names.model_has_permissions'),
			'permission_id',
			config('permission.column_names.model_morph_key')
		);
	}


	public static function findByName(string $name, $guardName = null): PermissionContract {
		$guardName = $guardName ?? Guard::getDefaultName(static::class);
		$permission = static::getPermissions(['name' => $name, 'guard_name' => $guardName])->first();
		if (!$permission) {
			throw PermissionDoesNotExist::create($name, $guardName);
		}

		return $permission;
	}






}
