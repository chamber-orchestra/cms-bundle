<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\DependencyInjection;

use ChamberOrchestra\CmsBundle\Security\Entity\AdminUser;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * What the layout needs to know about the application that uses it, so that the bundle never names a class or a route of the application:
 *
 *     chamber_orchestra_cms:
 *         sidebar_menu: App\Cms\Menu\SidebarNavigation   # the navigation (a menu-bundle navigation) shown in the sidebar
 *         search_route: cms_search                       # the route of the search box of the top bar (null: no box)
 *         admin_user_class: App\Entity\AdminUser         # the entity of the administrators (default: the AdminUser of the bundle)
 *         audit:                                         # the journal of what the administrators change (off by default)
 *             enabled: true
 *             path_prefix: /abc                          # only requests under this path are journalled (default: every request)
 *             ignore: [App\Entity\Setting]               # entities that are never written
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('chamber_orchestra_cms');
        $tree->getRootNode()
            ->children()
                ->scalarNode('sidebar_menu')->defaultNull()->info('Class of the navigation shown in the sidebar (null: an empty sidebar).')->end()
                ->scalarNode('admin_user_class')->defaultValue(AdminUser::class)->info('Entity of the administrators: AdminUser of the bundle or an entity of the application extending AbstractAdminUser.')->end()
                ->scalarNode('search_route')->defaultNull()->info('Route of the search box of the top bar; its JSON answer is [{group, title, items: [{label, sub, url}]}] (null: no search box).')->end()
                ->arrayNode('audit')
                    ->addDefaultsIfNotSet()
                    ->info('The journal of what the administrators change (table audit_log, page /audit under the prefix of the admin routes).')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('On: the table, the writing after every flush and the page exist. Off: none of them.')->end()
                        ->scalarNode('path_prefix')->defaultValue('/')->info('Only requests whose path starts with this are journalled.')->end()
                        ->arrayNode('ignore')->scalarPrototype()->end()->defaultValue([])->info('Entities that are never written (technical state).')->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
