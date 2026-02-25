<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<int|null, string>
 */
readonly class StringToNumberTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return (string) (int) $value;
    }

    public function reverseTransform(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $value = \preg_replace('~\D~', '', (string) $value);

        return '' === $value || null === $value ? null : (int) $value;
    }
}
