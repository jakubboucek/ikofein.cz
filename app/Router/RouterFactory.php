<?php /** @noinspection HtmlUnknownTag */

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

class RouterFactory
{
    use Nette\StaticClass;


    /**
     * @return RouteList
     */
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

        $router->addRoute(
            'home<? \.htm|\.php|>',
            [
                'presenter' => 'Static',
                'action' => 'default',
                'lang' => 'en',
            ],
            Route::ONE_WAY
        );

        $router->addRoute(
            'index<? \.htm|\.php|>',
            [
                'presenter' => 'Static',
                'action' => 'default',
                'page' => null,
                'lang' => 'cs',
            ],
            Route::ONE_WAY
        );

        $router->addRoute(
            'uvod<? \.htm|\.php|>',
            [
                'presenter' => 'Static',
                'action' => 'default',
                'page' => null,
                'lang' => 'cs',
            ],
            Route::ONE_WAY
        );

        $router->addRoute('[<lang (cs|cz|en)>/][<page [a-z]+>]<? \.htm|\.php|>', 'Static:default');

        return $router;
    }
}
