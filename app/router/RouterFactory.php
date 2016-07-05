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
		$router[] = new Route('[<lang (cz|cs|en)>/][<page [a-z]+>]<? \.htm|\.php|>', 'Static:default');
		return $router;
	}

}
