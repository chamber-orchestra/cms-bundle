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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @implements DataTransformerInterface<array<string, mixed>|null, string>
 */
readonly class ArrayToYamlStringTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return Yaml::dump($value);
    }

    /** @return array<string, mixed>|null */
    public function reverseTransform(mixed $value): ?array
    {
        if (!$value) {
            return null;
        }

        try {
            $string = Yaml::parse($value);
        } catch (ParseException $e) {
            throw new TransformationFailedException(\sprintf('Could not parse YAML string to array: %s', $e->getMessage()), 0, $e);
        }

        /** @var array<string, mixed>|null $parsed */
        $parsed = $string;

        return $parsed;
    }
}
