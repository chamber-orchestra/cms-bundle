<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$monoVendor = \dirname(__DIR__, 3).'/vendor/autoload.php';
$localVendor = \dirname(__DIR__).'/vendor/autoload.php';

$loader = require \file_exists($monoVendor) ? $monoVendor : $localVendor;

// The monorepo autoloader maps Tests\ to its own tests/ directory.
// Re-register so Tests\ also resolves from this bundle's tests/ directory.
$loader->addPsr4('Tests\\', [__DIR__]);
