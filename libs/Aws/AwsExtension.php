<?php

declare(strict_types=1);

/**
 * Copyright (c) 2016 Jakub Bouček (pan@jakubboucek.cz)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace JakubBoucek\Aws\DI;

use Nette;

/**
 * @author Jakub Bouček <pan@jakubboucek.cz>
 */
class AwsExtension extends Nette\DI\CompilerExtension
{

    /**
     * @var array
     */
    public $defaults = [
        'version' => 'latest',
        'region' => 'eu-west-1',
        'credentials' => [
            'key' => null,
            'secret' => null
        ]
    ];


    public function loadConfiguration(): void
    {
        $config = $this->validateConfig($this->defaults);
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('sdk'))
            ->setFactory(\Aws\Sdk::class, [$config]);
    }

}