<?php

declare(strict_types=1);

// Support both monorepo layout (../../vendor) and standalone checkout (vendor/)
$monoVendor = dirname(__DIR__, 3).'/vendor/autoload.php';
$localVendor = dirname(__DIR__).'/vendor/autoload.php';

$loader = require \file_exists($monoVendor) ? $monoVendor : $localVendor;

// The monorepo autoloader maps Tests\ to its own tests/ directory.
// Re-register so Tests\ also resolves from this bundle's tests/ directory.
$loader->addPsr4('Tests\\', [__DIR__]);
