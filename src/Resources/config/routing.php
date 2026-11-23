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
use ChamberOrchestra\CmsBundle\Controller\UploadController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(AdminLoginController::class, 'attribute');
    $routes->import(UploadController::class, 'attribute');
    $routes->import(AuditController::class, 'attribute'); // the page of the journal (404 while `audit.enabled` is off)
};
