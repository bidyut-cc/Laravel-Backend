<?php

namespace App\Models;

use App\Traits\Crud;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model {
	use HasPermissions;
	use RefreshesPermissionCache, Crud;
    use SoftDeletes;
	protected $guarded = ['id'];
	protected $fillable = ['name', 'guard_name', 'description'];
	protected $selectFields = ['id','name', 'guard_name', 'description'];
	public $searchable = ['name'];
	public $defaultSort = 'id';
	public $defaultSortOrder = 'desc';
	public $defaultPaginate = '10';
	public $model = 'role';
	protected static function booted()
    {
        static::creating(function ($role) {
            if (empty($role->guard_name)) {
                $role->guard_name = 'web';
            }
        });

        static::updating(function ($role) {
            if (empty($role->guard_name)) {
                $role->guard_name = 'web';
            }
        });
    }
	
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
			'placeholder' => 'Role',
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
			'required' => false,
			'value' => '',
			'width' => '50',
			'show_in_form' => true
		)
	);
	public function __construct(array $attributes = []) {
		$attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

		parent::__construct($attributes);

		$this->setTable(config('permission.table_names.roles'));
	}

	public static function create(array $attributes = []) {
		$attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

		if (static::where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
			throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
		}

		return static::query()->create($attributes);
	}


	public function permissions(): BelongsToMany {
		return $this->belongsToMany(
			config('permission.models.permission'),
			config('permission.table_names.role_has_permissions'),
			'role_id',
			'permission_id'
		);
	}

    public function permission(): BelongsToMany {
		return $this->belongsToMany(
		Permission::class,
        'role_has_permissions',
			'role_id',
			'permission_id'
		);
	}


	// public function users(): MorphToMany {
	// 	return $this->morphedByMany(
	// 		getModelForGuard($this->attributes['guard_name']),
	// 		'model',
	// 		config('permission.table_names.model_has_roles'),
	// 		'role_id',
	// 		config('permission.column_names.model_morph_key')
	// 	);
	// }

    public function users(): BelongsToMany {
		return $this->belongsToMany(
			User::class,
			'model_has_roles',
			'role_id',
			'model_id',
		);
	}





}
