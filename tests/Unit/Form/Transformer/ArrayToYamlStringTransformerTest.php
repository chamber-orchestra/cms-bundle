<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Form\Transformer;

use ChamberOrchestra\CmsBundle\Form\Transformer\ArrayToYamlStringTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class ArrayToYamlStringTransformerTest extends TestCase
{
    private ArrayToYamlStringTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new ArrayToYamlStringTransformer();
    }

    public function testTransformNullReturnsEmptyString(): void
    {
        self::assertSame('', $this->transformer->transform(null));
    }

    public function testTransformArrayReturnsYamlString(): void
    {
        $result = $this->transformer->transform(['key' => 'value']);

        self::assertIsString($result);
        self::assertStringContainsString('key:', $result);
    }

    public function testReverseTransformEmptyStringReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(''));
    }

    public function testReverseTransformNullReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(null));
    }

    public function testReverseTransformValidYamlReturnsArray(): void
    {
        $result = $this->transformer->reverseTransform("key: value\n");

        self::assertSame(['key' => 'value'], $result);
    }

    public function testReverseTransformInvalidYamlThrows(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/Could not parse YAML/');

        $this->transformer->reverseTransform('invalid: yaml: :');
    }
}
