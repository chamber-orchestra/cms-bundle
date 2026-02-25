<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ChamberOrchestra\CmsBundle\Controller\CrudControllerInterface;
use ChamberOrchestra\CmsBundle\EventSubscriber\SetVersionSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->instanceof(CrudControllerInterface::class)
        ->tag('cms.crud_controller')
        ->lazy();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->load('ChamberOrchestra\\CmsBundle\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,Exception,Maker,Controller,Events,Form/Dto,Form/Normalizer,Form/Transformer,Regex,Processor/Reflection,Configurator,EntityRepository,tests}');

    $services->set(SetVersionSubscriber::class)
        ->autoconfigure()
        ->autowire(false)
        ->args(['build_id']);
};
