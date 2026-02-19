<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

readonly class ArrayToYamlStringTransformer implements DataTransformerInterface
{
    public function transform($value): string
    {
        if (null !== $value && !\is_array($value)) {
            throw new TransformationFailedException('Expected a array or null.');
        }

        if (null === $value) {
            return '';
        }

        return Yaml::dump($value);
    }

    public function reverseTransform($value): ?array
    {
        if (null !== $value && !\is_string($value)) {
            throw new TransformationFailedException('Expected a string or null.');
        }

        if (!$value) {
            return null;
        }

        try {
            $string = Yaml::parse($value);
        } catch (ParseException $e) {
            throw new TransformationFailedException(\sprintf('Could not parse YAML string to array: %s', $e->getMessage()), 0, $e);
        }

        return $string;
    }
}
