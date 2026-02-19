<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Normalizer;

readonly class WysiwygNormalizer
{
    public function __invoke(string $value): string
    {
        return \trim(\strip_tags(\str_replace('&nbsp;', '', $value)));
    }
}