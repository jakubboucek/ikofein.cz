<?php

namespace App;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class RouterFactory
{
	use Nette\StaticClass;

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter()
	{
		$router = new RouteList;
		$router[] = new Route('admin/<presenter>/<action>', [
			'module' => 'Admin',
			'presenter' => 'Dashboard',
			'action' => 'default'
		]);
		$router[] = new Route('[<lang (cs|cz|en)>/][<page [a-z]+>]<? \.htm|\.php|>', 'Static:default');
		return $router;
	}

}
