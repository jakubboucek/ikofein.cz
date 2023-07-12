<?php

declare(strict_types=1);

/**
 * Copyright (c) 2016 Jakub Bouček (pan@jakubboucek.cz)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace JakubBoucek\Aws\DI;

use Nette;
use Nette\Schema\Expect;

/**
 * @author Jakub Bouček <pan@jakubboucek.cz>
 */
class AwsExtension extends Nette\DI\CompilerExtension
{

    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Expect::structure(
            [
                'version' => Expect::string()->default('latest'),
                'region' => Expect::string()->default('eu-west-1'),
                'credentials' => Expect::structure(
                    [
                        'key' => Expect::string()->required(),
                        'secret' => Expect::string()->required(),
                    ]
                )->castTo('array'),
            ]
        )->castTo('array');
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('sdk'))
            ->setFactory(\Aws\Sdk::class, [$this->config]);
    }

}
