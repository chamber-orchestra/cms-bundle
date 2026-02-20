<?php

declare(strict_types=1);

namespace Tests\Unit\Form\Transformer;

use ChamberOrchestra\CmsBundle\Form\Transformer\StringToNumberTransformer;
use PHPUnit\Framework\TestCase;

final class StringToNumberTransformerTest extends TestCase
{
    private StringToNumberTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new StringToNumberTransformer();
    }

    public function testTransformIntReturnsString(): void
    {
        self::assertSame('42', $this->transformer->transform(42));
    }

    public function testReverseTransformNullReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(null));
    }

    public function testReverseTransformEmptyStringReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(''));
    }

    public function testReverseTransformZeroStringReturnsZero(): void
    {
        // Regression: falsy-check bug would have returned null for '0'
        self::assertSame(0, $this->transformer->reverseTransform('0'));
    }

    public function testReverseTransformNumericStringReturnsInt(): void
    {
        self::assertSame(123, $this->transformer->reverseTransform('123'));
    }

    public function testReverseTransformFormattedNumberStripsNonDigits(): void
    {
        // preg_replace('~\D~', '') strips ALL non-digits: '$1,234' → '1234'
        self::assertSame(1234, $this->transformer->reverseTransform('$1,234'));
    }

    public function testReverseTransformAllNonDigitsReturnsNull(): void
    {
        self::assertNull($this->transformer->reverseTransform('abc'));
    }
}
