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
 *             record_ip: true                            # the address of the client goes into every line (default: false)
 *             anonymize_ip: true                         # ... with its last part cut off (default: true)
 *             keyed_hash_secret: '%env(AUDIT_SECRET)%'   # the secret of #[AuditTransform(KEYED_HASH)] (default: the secret of the kernel)
 *             silent_failures: true                      # a line that cannot be written is logged, the change stays saved (false: an error)
 *             integrity:                                 # a seal on every line, checked by the page and by `cms:audit:check`
 *                 enabled: true
 *                 secret: '%env(AUDIT_SECRET)%'          # required when enabled
 *
 * What is journalled is chosen on the entities: #[Auditable] on the class, #[AuditIgnore] and #[AuditTransform(...)] on its properties.
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
                        ->booleanNode('record_ip')->defaultFalse()->info('Write the address of the client with every line.')->end()
                        ->booleanNode('anonymize_ip')->defaultTrue()->info('Cut the last part off the address (IPv4: the last byte; IPv6: the last 80 bits).')->end()
                        ->scalarNode('keyed_hash_secret')->defaultNull()->info('The secret of #[AuditTransform(AuditTransform::KEYED_HASH)]; null: the secret of the kernel.')->end()
                        ->booleanNode('silent_failures')->defaultTrue()->info('true: a line that cannot be written is logged and the saved change stays saved; false: it is an error (development, tests).')->end()
                        ->arrayNode('integrity')
                            ->addDefaultsIfNotSet()
                            ->info('A seal (HMAC) on every line: a line changed or made by hand in the table does not fit it.')
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->scalarNode('secret')->defaultNull()->info('The secret of the seal (required when enabled).')->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => $v['enabled'] && (null === $v['secret'] || '' === $v['secret']))
                                ->thenInvalid('audit.integrity.secret is required when audit.integrity.enabled is true.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
