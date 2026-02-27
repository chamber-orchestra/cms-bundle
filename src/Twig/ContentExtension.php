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
use Twig\TwigFunction;

class ContentExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('content_entry', [ContentRuntime::class, 'getEntry']),
            new TwigFunction('content_entries_for', [ContentRuntime::class, 'getEntriesFor']),
            new TwigFunction('content_render_entry', [ContentRuntime::class, 'renderEntry'], ['is_safe' => ['html']]),
        ];
    }
}
