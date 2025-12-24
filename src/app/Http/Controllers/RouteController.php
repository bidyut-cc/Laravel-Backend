<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class RouteController extends Controller {
	public function initiate($contollerName, $methodName = null) {
		try {
			$controller = 'App\Http\Controllers\\' . Str::plural(ucfirst($contollerName)) . 'Controller';
			$action = $methodName ?? 'getListing';

			$container = app();
			$route = $container->make(\Illuminate\Routing\Route::class);

			$controllerInstance = $container->make($controller);

			// 🔥 AUTO SET MODEL from URL
			$controllerInstance->model = Str::singular($contollerName);

			return (new ControllerDispatcher($container))->dispatch($route, $controllerInstance, $action);
		} catch (\Exception $e) {
			return response([
				'status' => false,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			], 500);
		}
	}
}

