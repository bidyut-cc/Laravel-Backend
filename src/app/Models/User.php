<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\Crud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens, Crud, SoftDeletes, HasRoles, LogsActivity;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['two_factor_expires_at'];

    protected static $logOnlyDirty = true;
    protected static $logAttributes = ['first_name', 'last_name', 'email', "profile_image"];

    public $model = 'user';
    protected $fillable = ['first_name','middle_name','last_name','email','password',"profile_image",'google_uid','two_factor_code','two_factor_expires_at','active_status','two_factor_enabled'];
    protected $searchable = ['first_name', 'middle_name','last_name', 'email'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime'];
    public $default_sort = 'id';
	public $default_sort_order = 'desc';
	public $defaultPaginate = 10;
    public $selectFields = ['id',  'first_name', 'middle_name','last_name', 'email', 'email_verified_at', 'profile_image', 'two_factor_enabled', 'active_status'];
    public $fieldsArray = array(
        'id' => array(
            'field_name' => 'id',
            'db_name' => 'id',
            'type' => 'text',
            'placeholder' => 'Id',
            'listing' => true,
            'show_in_form' => false,
            'sort' => true,
            'default_sort' => true,
            'required' => false,
            'value' => '',
            'width' => '50'
        ),
        'first_name' => array(
            'field_name' => 'first_name',
            'db_name' => 'first_name',
            'type' => 'text',
            'placeholder' => 'First Name',
            'listing' => true,
            'sort' => true,
            'default_sort' => false,
            'required' => true,
            'value' => '',
            'width' => '50',
            'show_in_form' => false
        ),
        'last_name' => array(
            'field_name' => 'last_name',
            'db_name' => 'last_name',
            'type' => 'text',
            'placeholder' => 'Last Name',
            'listing' => true,
            'sort' => true,
            'default_sort' => false,
            'required' => true,
            'value' => '',
            'width' => '50',
            'show_in_form' => false
        ),
        'email' => array(
            'field_name' => 'email',
            'db_name' => 'email',
            'type' => 'email',
            'placeholder' => 'Email',
            'listing' => true,
            'sort' => true,
            'default_sort' => false,
            'required' => true,
            'value' => '',
            'width' => '50',
            'show_in_form' => false
        ),
        'email_verified_at' => array(
            'field_name' => 'email_verified_at',
            'db_name' => 'email_verified_at',
            'type' => 'email',
            'placeholder' => 'Email Verified At',
            'listing' => false,
            'sort' => true,
            'default_sort' => false,
            'show_in_form' => false,
            'required' => true,
            'value' => '',
            'width' => '50'
        ),
        'password' => array(
            'field_name' => 'password',
            'db_name' => 'password',
            'type' => 'password',
            'placeholder' => 'Password',
            'listing' => false,
            'sort' => true,
            'default_sort' => false,
            'required' => false,
            'value' => '',
            'width' => '50',
            'show_in_form' => false
        ),
        'role' => array(
            'field_name' => 'role',
            'db_name' => 'role',
            'type' => 'role',
            'placeholder' => 'Role',
            'listing' => true,
            'sort' => true,
            'default_sort' => false,
            'required' => false,
            'value' => '',
            'width' => '50',
            'show_in_form' => false,
        )
    );
    public $guard_name = 'sanctum';



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()                // log all attributes
            ->useLogName('users')    // optional
            ->setDescriptionForEvent(fn(string $eventName) => "Module has been {$eventName}");
    }
}
