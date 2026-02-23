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
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<array<string, mixed>|null, string>
 */
readonly class ArrayToJsonStringTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (!\is_array($value)) {
            throw new TransformationFailedException(\sprintf('Expected an array, got "%s".', \get_debug_type($value)));
        }

        return (string) \json_encode($value, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed>|null */
    public function reverseTransform(mixed $value): ?array
    {
        if (!$value) {
            return null;
        }

        $result = \json_decode($value, true);

        if (\JSON_ERROR_NONE !== \json_last_error()) {
            throw new TransformationFailedException(\sprintf('Could not parse string to valid JSON format. %s', \json_last_error_msg()));
        }

        /** @var array<string, mixed>|null $parsed */
        $parsed = $result;

        return $parsed;
    }
}
