<?php /** @noinspection HtmlUnknownTag */

declare(strict_types=1);

namespace App;

use Nette;
use Nette\Application\IRouter;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

class RouterFactory
{
    use Nette\StaticClass;


    /**
     * @return IRouter
     */
    public static function createRouter(): IRouter
    {
        $router = new RouteList;

        $router[] = new Route(
            'admin/<presenter>/<action>',
            [
                'module' => 'Admin',
                'presenter' => 'Dashboard',
                'action' => 'default'
            ]
        );

        $router[] = new Route(
            'home<? \.htm|\.php|>',
            [
                'presenter' => 'Static',
                'action' => 'default',
                'lang' => 'en',
            ],
            Route::ONE_WAY
        );

        $router[] = new Route(
            'index<? \.htm|\.php|>',
            [
                'presenter' => 'Static',
                'action' => 'default',
                'page' => null,
                'lang' => 'cs',
            ],
            Route::ONE_WAY
        );

        $router[] = new Route(
            'uvod<? \.htm|\.php|>',
            [
                'presenter' => 'Static',
                'action' => 'default',
                'page' => null,
                'lang' => 'cs',
            ],
            Route::ONE_WAY
        );
        $router[] = new Route('[<lang (cs|cz|en)>/][<page [a-z]+>]<? \.htm|\.php|>', 'Static:default');

        return $router;
    }
}
