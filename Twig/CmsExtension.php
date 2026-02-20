<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CmsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('_read', [CmsRuntime::class, 'read']),
        ];
    }
}