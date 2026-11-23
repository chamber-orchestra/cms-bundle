<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * `cms` in the templates of the layout: what the application configured (`chamber_orchestra_cms`), `cms.sidebar_menu` and `cms.search_route`.
 */
final class CmsGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly ?string $sidebarMenu, private readonly ?string $searchRoute)
    {
    }

    public function getGlobals(): array
    {
        return ['cms' => ['sidebar_menu' => $this->sidebarMenu, 'search_route' => $this->searchRoute]];
    }
}
