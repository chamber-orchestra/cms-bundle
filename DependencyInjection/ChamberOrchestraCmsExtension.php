<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\DependencyInjection;

use ChamberOrchestra\CmsBundle\Controller\AbstractCrudController;
use ChamberOrchestra\CmsBundle\Controller\CrudControllerInterface;
use ChamberOrchestra\CmsBundle\EventSubscriber\SetVersionSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;

class ChamberOrchestraCmsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $container
            ->registerForAutoconfiguration(CrudControllerInterface::class)
            ->addTag(AbstractCrudController::CRUD_CONTROLLER_TAG)
            ->setPublic(true)
            ->setLazy(false);

        $this->registerCmsRuntimeCache($container);
    }

    private function registerCmsRuntimeCache(ContainerBuilder $container): void
    {
        $version = new Parameter('container.build_id');
        $container->getDefinition(SetVersionSubscriber::class)->replaceArgument(0, $version);
    }
}
