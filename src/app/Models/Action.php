<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Crud;
use Illuminate\Database\Eloquent\Model;

class Action extends Model {

	use SoftDeletes, Crud;
	protected $fillable = ['name', 'code', 'description'];
	public $searchable = ['name', 'code', 'description'];
	public $defaultSort = 'id';
	public $defaultSortOrder = 'desc';
	public $defaultPaginate = '100';
	public $model = 'action';
	public $fieldsArray = array(
		'id' => array(
			'field_name' => 'id',
			'db_name' => 'id',
			'type' => 'text',
			'placeholder' => 'Id',
			'show_in_form' => false,
			'listing' => true,
			'sort' => true,
			'required' => false,
			'value' => '',
			'width' => '50'
		),
		'name' => array(
			'field_name' => 'name',
			'db_name' => 'name',
			'type' => 'text',
			'placeholder' => 'Action',
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
}
