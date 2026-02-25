<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use ChamberOrchestra\CmsBundle\Processor\Utils\CrudUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;

final class CrudUtilsSyncTest extends TestCase
{
    private CrudUtils $utils;

    protected function setUp(): void
    {
        $metaFactory = $this->createMock(ClassMetadataFactory::class);
        $metaFactory->method('isTransient')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metaFactory);

        $this->utils = new CrudUtils($em, new Instantiator());
    }

    public function testCopiesScalarPropertiesByNameIntersection(): void
    {
        $source = new SyncSource();
        $source->title = 'Hello';
        $source->enabled = false;

        $target = new SyncTarget();
        $this->utils->sync($source, $target);

        self::assertSame('Hello', $target->title);
        self::assertFalse($target->enabled);
    }

    public function testSkipsPropertiesNotInBothObjects(): void
    {
        $source = new SyncSource();
        $source->title = 'X';
        $source->onlyInSource = 'ignored';

        $target = new SyncTarget();
        $target->onlyInTarget = 'original';

        $this->utils->sync($source, $target);

        self::assertSame('original', $target->onlyInTarget);
    }

    public function testSkipsWhenSourceValueEqualsTargetValue(): void
    {
        $source = new SyncSource();
        $source->title = 'same';

        $target = new SyncTarget();
        $target->title = 'same';

        // No exception means no write attempt; value stays same
        $this->utils->sync($source, $target);

        self::assertSame('same', $target->title);
    }

    public function testNullSourceValueOverwritesNonNullTarget(): void
    {
        $source = new SyncSource();
        $source->title = '';
        $source->slug = null;

        $target = new SyncTarget();
        $target->title = '';
        $target->slug = 'existing';

        $this->utils->sync($source, $target);

        self::assertNull($target->slug);
    }

    public function testCollectionReconciliation(): void
    {
        $source = new CollectionSource();
        $source->tags = new ArrayCollection(['php', 'symfony']);

        $target = new CollectionTarget();
        $target->tags = new ArrayCollection(['php', 'old-tag']);

        $this->utils->sync($source, $target);

        $values = $target->tags->toArray();
        self::assertContains('php', $values);
        self::assertContains('symfony', $values);
        self::assertNotContains('old-tag', $values);
    }
}

// --------------- fixtures ---------------

class SyncSource
{
    public string $title = '';
    public bool $enabled = true;
    public ?string $slug = null;
    public string $onlyInSource = '';
}

class SyncTarget
{
    public string $title = '';
    public bool $enabled = true;
    public ?string $slug = null;
    public string $onlyInTarget = '';
}

class CollectionSource
{
    public \Doctrine\Common\Collections\Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}

class CollectionTarget
{
    public \Doctrine\Common\Collections\Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}
