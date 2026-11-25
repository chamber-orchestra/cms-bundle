<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\DependencyInjection;

use ChamberOrchestra\CmsBundle\Audit\AuditMetadata;
use ChamberOrchestra\CmsBundle\Audit\AuditSigner;
use ChamberOrchestra\CmsBundle\Audit\AuditSubscriber;
use ChamberOrchestra\CmsBundle\Audit\AuditTransformer;
use ChamberOrchestra\CmsBundle\Audit\AuditWriter;
use ChamberOrchestra\CmsBundle\Command\AuditCheckCommand;
use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Controller\CrudControllerInterface;
use ChamberOrchestra\CmsBundle\EventSubscriber\SetVersionSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

class ChamberOrchestraCmsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * The journal (`audit.enabled`) brings its own table: its entity is mapped and its migration registered only when it is on.
     */
    public function prepend(ContainerBuilder $container): void
    {
        /** @var array{audit: array{enabled: bool}} $config */
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        if (!$config['audit']['enabled']) {
            return;
        }
        $container->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['CmsAudit' => [
            'is_bundle' => false,
            'type' => 'attribute',
            'dir' => __DIR__.'/../Audit/Entity',
            'prefix' => 'ChamberOrchestra\\CmsBundle\\Audit\\Entity',
        ]]]]);
        if ($container->hasExtension('doctrine_migrations')) {
            $container->prependExtensionConfig('doctrine_migrations', ['migrations_paths' => ['ChamberOrchestra\\CmsBundle\\Migrations' => __DIR__.'/../Resources/migrations']]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{sidebar_menu: ?string, search_route: ?string, admin_user_class: string, audit: array{enabled: bool, path_prefix: string, record_ip: bool, anonymize_ip: bool, keyed_hash_secret: ?string, silent_failures: bool, integrity: array{enabled: bool, secret: ?string}}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('chamber_orchestra_cms.sidebar_menu', $config['sidebar_menu']);
        $container->setParameter('chamber_orchestra_cms.search_route', $config['search_route']);
        $container->setParameter('chamber_orchestra_cms.admin_user_class', $config['admin_user_class']);
        $container->setParameter('chamber_orchestra_cms.audit.enabled', $config['audit']['enabled']);
        $container->setParameter('chamber_orchestra_cms.audit.integrity', $config['audit']['enabled'] && $config['audit']['integrity']['enabled']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $container
            ->registerForAutoconfiguration(CrudControllerInterface::class)
            ->addTag(AbstractCrudController::CRUD_CONTROLLER_TAG)
            ->setPublic(true)
            ->setLazy(false);

        if ($config['audit']['enabled']) {
            $audit = $config['audit'];
            $container->setParameter('chamber_orchestra_cms.audit.integrity', $audit['integrity']['enabled']);
            $container->register(AuditMetadata::class, AuditMetadata::class);
            $container->register(AuditTransformer::class, AuditTransformer::class)
                ->setArgument('$keyedHashSecret', $audit['keyed_hash_secret'] ?? new Parameter('kernel.secret'));
            $container->register(AuditSigner::class, AuditSigner::class)
                ->setArgument('$enabled', $audit['integrity']['enabled'])
                ->setArgument('$secret', $audit['integrity']['secret'] ?? '');
            $container->register(AuditWriter::class, AuditWriter::class)->setAutowired(true);
            $container->register(AuditSubscriber::class, AuditSubscriber::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setArgument('$pathPrefix', $audit['path_prefix'])
                ->setArgument('$userClass', $config['admin_user_class'])
                ->setArgument('$metadata', new Reference(AuditMetadata::class))
                ->setArgument('$transformer', new Reference(AuditTransformer::class))
                ->setArgument('$recordIp', $audit['record_ip'])
                ->setArgument('$anonymizeIp', $audit['anonymize_ip'])
                ->setArgument('$silentFailures', $audit['silent_failures'])
                ->setArgument('$logger', new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE));
            $container->register(AuditCheckCommand::class, AuditCheckCommand::class)
                ->setAutowired(true)
                ->addTag('console.command');
        }

        $this->registerCmsRuntimeCache($container);
    }

    private function registerCmsRuntimeCache(ContainerBuilder $container): void
    {
        $version = new Parameter('container.build_id');
        $container->getDefinition(SetVersionSubscriber::class)->replaceArgument(0, $version);
    }
}
