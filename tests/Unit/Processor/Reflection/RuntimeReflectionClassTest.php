<?php

declare(strict_types=1);

namespace Tests\Unit\Processor\Reflection;

use ChamberOrchestra\CmsBundle\Processor\Reflection\RuntimeReflectionClass;
use PHPUnit\Framework\TestCase;

final class RuntimeReflectionClassTest extends TestCase
{
    public function testGetAccessiblePropertiesIncludesParentProperties(): void
    {
        $refl = new RuntimeReflectionClass(ChildFixture::class);
        $props = $refl->getAccessibleProperties();

        self::assertArrayHasKey('parentProp', $props);
        self::assertArrayHasKey('childProp', $props);
    }

    public function testGetAccessiblePropertiesNoDuplicates(): void
    {
        $refl = new RuntimeReflectionClass(ChildFixtureWithShadow::class);
        $props = $refl->getAccessibleProperties();

        // 'overridden' appears in both parent and child; only one entry expected
        $names = \array_keys($props);
        self::assertSame(\array_unique($names), $names);
    }

    public function testGetAccessiblePropertiesAreReadWritable(): void
    {
        $refl = new RuntimeReflectionClass(ParentFixture::class);
        $props = $refl->getAccessibleProperties();

        $obj = new ParentFixture();
        $props['parentProp']->setValue($obj, 'hello');
        self::assertSame('hello', $props['parentProp']->getValue($obj));
    }

    public function testGetAccessiblePropertyReturnsSingleProperty(): void
    {
        $refl = new RuntimeReflectionClass(ParentFixture::class);
        $prop = $refl->getAccessibleProperty('parentProp');

        self::assertSame('parentProp', $prop->getName());
    }

    public function testGetAccessiblePropertiesOnClassWithNoParent(): void
    {
        $refl = new RuntimeReflectionClass(ParentFixture::class);
        $props = $refl->getAccessibleProperties();

        self::assertArrayHasKey('parentProp', $props);
        self::assertCount(1, $props);
    }
}

// --------------- fixtures ---------------

class ParentFixture
{
    private string $parentProp = '';
}

class ChildFixture extends ParentFixture
{
    private string $childProp = '';
}

class ChildFixtureWithShadow extends ParentFixture
{
    // shadows parent's $parentProp intentionally to test deduplication
    private string $parentProp = '';
}
