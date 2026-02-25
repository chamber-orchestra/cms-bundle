<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ChamberOrchestra\CmsBundle\Maker\Generator\TranslationGenerator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->load('ChamberOrchestra\\CmsBundle\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,ExceptionInterface}');

    $services->alias(DoctrineHelper::class, 'maker.doctrine_helper');
    $services->alias(FileManager::class, 'maker.file_manager');

    $services->set(TranslationGenerator::class)
        ->arg('$fileManager', service('maker.file_manager'))
        ->arg('$translationsPath', 'translations/');
};
