<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Crud;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Module extends Model {
	use SoftDeletes, Crud, LogsActivity;
	protected static $logAttributes = ['name', 'code', 'description'];
	protected $fillable = ['name', 'code', 'description'];
	public $searchable = ['name', 'code', 'description'];
	public $defaultSort = 'id';
	public $defaultSortOrder = 'desc';
	public $defaultPaginate = '10';
	public $model = 'module';
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
			'placeholder' => 'Name',
			'listing' => true,
			'sort' => true,
			'required' => true,
			'value' => '',
			'width' => '50'
		),
		'code' => array(
			'field_name' => 'code',
			'db_name' => 'code',
			'type' => 'text',
			'placeholder' => 'Code',
			'listing' => true,
			'sort' => true,
			'required' => true,
			'value' => '',
			'width' => '50'
		),
		'description' => array(
			'field_name' => 'description',
			'db_name' => 'description',
			'type' => 'textarea',
			'placeholder' => 'Description',
			'listing' => true,
			'sort' => true,
			'defaultSort' => false,
			'required' => true,
			'value' => '',
			'width' => '50'
		)
	);

	// public $additionalRulesArray = [
	// 	'name'        => 'string',
	// 	'code'        => 'string',
	// 	'description' => 'string',
	// ];
	public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()                // log all attributes
            ->useLogName('module')    // optional
            ->setDescriptionForEvent(fn(string $eventName) => "Module has been {$eventName}");
    }

}
