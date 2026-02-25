<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Processor;

use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use PHPUnit\Framework\TestCase;

final class InstantiatorTest extends TestCase
{
    private Instantiator $instantiator;

    protected function setUp(): void
    {
        $this->instantiator = new Instantiator();
    }

    public function testInstantiatesClassWithNoConstructor(): void
    {
        $obj = $this->instantiator->instantiate(NoCtorFixture::class, []);

        self::assertInstanceOf(NoCtorFixture::class, $obj);
    }

    public function testInstantiatesClassFromMatchingArrayKeys(): void
    {
        $obj = $this->instantiator->instantiate(RequiredParamFixture::class, ['name' => 'Alice']);

        self::assertInstanceOf(RequiredParamFixture::class, $obj);
        self::assertSame('Alice', $obj->name);
    }

    public function testUsesDefaultValueWhenKeyAbsent(): void
    {
        $obj = $this->instantiator->instantiate(OptionalParamFixture::class, []);

        self::assertSame('default', $obj->name);
    }

    public function testUsesNullForNullableParamWhenKeyAbsent(): void
    {
        $obj = $this->instantiator->instantiate(NullableParamFixture::class, []);

        self::assertNull($obj->name);
    }

    public function testFallsBackToTypeMatchWhenKeyAbsent(): void
    {
        $dep = new TypeDep();
        $obj = $this->instantiator->instantiate(TypeMatchFixture::class, ['dep' => $dep]);

        self::assertSame($dep, $obj->dep);
    }

    public function testThrowsRuntimeExceptionForUnresolvableRequiredParam(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->instantiator->instantiate(RequiredParamFixture::class, []);
    }

    public function testCachesFactory(): void
    {
        // First instantiation builds the factory
        $obj1 = $this->instantiator->instantiate(NoCtorFixture::class, []);
        // Second should reuse cached factory without error
        $obj2 = $this->instantiator->instantiate(NoCtorFixture::class, []);

        self::assertInstanceOf(NoCtorFixture::class, $obj1);
        self::assertInstanceOf(NoCtorFixture::class, $obj2);
        // Different instances
        self::assertNotSame($obj1, $obj2);
    }
}

// --------------- fixtures ---------------

class NoCtorFixture
{
}

class RequiredParamFixture
{
    public function __construct(public readonly string $name)
    {
    }
}

class OptionalParamFixture
{
    public function __construct(public readonly string $name = 'default')
    {
    }
}

class NullableParamFixture
{
    public function __construct(public readonly ?string $name)
    {
    }
}

class TypeDep
{
}

class TypeMatchFixture
{
    public function __construct(public readonly TypeDep $dep)
    {
    }
}
