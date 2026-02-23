<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Normalizer;

readonly class WysiwygNormalizer
{
    public function __invoke(string $value): string
    {
        $value = \str_replace('&nbsp;', ' ', $value);
        $value = \preg_replace('/&[a-zA-Z]+;/', '', $value) ?? $value;

        return \trim(\strip_tags($value));
    }
}
