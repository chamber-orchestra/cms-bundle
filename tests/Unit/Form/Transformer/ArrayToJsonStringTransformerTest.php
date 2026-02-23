<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Form\Transformer;

use ChamberOrchestra\CmsBundle\Form\Transformer\ArrayToJsonStringTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class ArrayToJsonStringTransformerTest extends TestCase
{
    private ArrayToJsonStringTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new ArrayToJsonStringTransformer();
    }

    public function testTransformNullReturnsEmptyString(): void
    {
        self::assertSame('', $this->transformer->transform(null));
    }

    public function testTransformArrayReturnsJsonString(): void
    {
        $result = $this->transformer->transform(['a' => 1]);

        self::assertIsString($result);
        self::assertSame(['a' => 1], \json_decode($result, true));
    }

    public function testTransformStringThrows(): void
    {
        $this->expectException(TransformationFailedException::class);

        $this->transformer->transform('string');
    }

    public function testReverseTransformEmptyStringReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(''));
    }

    public function testReverseTransformNullReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(null));
    }

    public function testReverseTransformValidJsonReturnsArray(): void
    {
        $result = $this->transformer->reverseTransform('{"k":"v"}');

        self::assertSame(['k' => 'v'], $result);
    }

    public function testReverseTransformInvalidJsonThrows(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/Could not parse string to valid JSON/');

        $this->transformer->reverseTransform('invalid');
    }

    public function testReverseTransformPreservesEmbeddedNewlines(): void
    {
        $json = '{"text":"line1\nline2"}';
        $result = $this->transformer->reverseTransform($json);

        self::assertIsArray($result);
        self::assertStringContainsString("\n", $result['text']);
    }
}
