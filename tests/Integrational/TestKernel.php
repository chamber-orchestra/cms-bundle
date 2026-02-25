<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test_secret',
            'test' => true,
        ]);

        $dbUrl = $_ENV['CMS_BUNDLE_TEST_DB_URL'] ?? $_SERVER['CMS_BUNDLE_TEST_DB_URL'] ?? null;

        $container->extension('doctrine', [
            'dbal' => null !== $dbUrl
                ? ['url' => $dbUrl]
                : [
                    'driver' => 'pdo_pgsql',
                    'host' => '/var/run/postgresql',
                    'dbname' => 'cms_bundle_test',
                    'user' => \get_current_user(),
                    'server_version' => '17',
                ],
            'orm' => [
                'entity_managers' => [
                    'default' => [
                        'mappings' => [
                            'Tests' => [
                                'type' => 'attribute',
                                'dir' => '%kernel.project_dir%/tests/Integrational/Entity',
                                'prefix' => 'Tests\\Integrational\\Entity',
                                'alias' => 'Tests',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $container->services()
            ->alias(EntityManagerInterface::class, 'doctrine.orm.entity_manager')
            ->public();

        $container->services()
            ->alias(EventDispatcherInterface::class, 'event_dispatcher')
            ->public();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
