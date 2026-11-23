<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ChamberOrchestra\CmsBundle\Controller\AdminLoginController;
use ChamberOrchestra\CmsBundle\Controller\AuditController;
use ChamberOrchestra\CmsBundle\Controller\ContentTypeController;
use ChamberOrchestra\CmsBundle\Controller\UploadController;
use ChamberOrchestra\CmsBundle\Controller\CrudControllerInterface;
use ChamberOrchestra\CmsBundle\EventSubscriber\SetVersionSubscriber;
use ChamberOrchestra\CmsBundle\Twig\CmsGlobalsExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

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
        ->exclude('../../{DependencyInjection,Audit,Resources,Exception,Maker,Controller,Events,Form/Dto,Form/Normalizer,Form/Transformer,Regex,Processor/Reflection,Configurator,EntityRepository,Entity,Security/Entity,Api/View,tests}');

    $services->set(AdminLoginController::class)
        ->autowire()
        ->autoconfigure();

    $services->set(UploadController::class)
        ->autowire()
        ->autoconfigure();

    // the journal's page: the service always exists, the controller says 404 while `audit.enabled` is off
    $services->set(AuditController::class)
        ->autowire()
        ->autoconfigure();

    $services->set(ContentTypeController::class)
        ->autowire()
        ->autoconfigure();

    $services->set(CmsGlobalsExtension::class)
        ->autoconfigure()
        ->autowire(false)
        ->args([param('chamber_orchestra_cms.sidebar_menu'), param('chamber_orchestra_cms.search_route')]);

    $services->set(SetVersionSubscriber::class)
        ->autoconfigure()
        ->autowire(false)
        ->args(['build_id']);
};
