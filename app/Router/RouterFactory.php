<?php /** @noinspection HtmlUnknownTag */

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

class RouterFactory
{
    use Nette\StaticClass;


    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        $router->addRoute(
            'admin/<presenter>/<action>',
            [
                'module' => 'Admin',
                'presenter' => 'Dashboard',
                'action' => 'default'
            ]
        );

        $router->addRoute('[<lang [a-z]{2}>/][<page=homepage>]', 'Static:default');

        return $router;
    }
}
