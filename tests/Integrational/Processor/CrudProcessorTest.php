<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\CmsBundle\Events\CreateEvent;
use ChamberOrchestra\CmsBundle\Events\DeleteEvent;
use ChamberOrchestra\CmsBundle\Events\PostSyncEvent;
use ChamberOrchestra\CmsBundle\Events\SyncEvent;
use ChamberOrchestra\CmsBundle\Events\UpdateEvent;
use ChamberOrchestra\CmsBundle\Processor\CrudProcessor;
use ChamberOrchestra\CmsBundle\Processor\Instantiator;
use ChamberOrchestra\CmsBundle\Processor\Utils\CrudUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Integrational\Dto\TestArticleDto;
use Tests\Integrational\Entity\TestArticle;
use Tests\Integrational\Entity\TestSoftDeleteArticle;

final class CrudProcessorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EventDispatcherInterface $dispatcher;
    private CrudProcessor $processor;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->dispatcher = self::getContainer()->get(EventDispatcherInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($this->getClassMetadatas());
        $schemaTool->createSchema($this->getClassMetadatas());

        $instantiator = new Instantiator();
        $utils = new CrudUtils($this->em, $instantiator);
        $this->processor = new CrudProcessor($this->em, $this->dispatcher, $utils, $instantiator);
    }

    private function getClassMetadatas(): array
    {
        return [
            $this->em->getClassMetadata(TestArticle::class),
            $this->em->getClassMetadata(TestSoftDeleteArticle::class),
        ];
    }

    public function testCreatePersistsEntityAndSyncsDto(): void
    {
        $dto = new TestArticleDto();
        $dto->title = 'New article';

        $entity = $this->processor->create($dto);

        self::assertInstanceOf(TestArticle::class, $entity);
        self::assertSame('New article', $entity->title);
        self::assertNotNull($entity->getId());
    }

    public function testCreateFiresEventsInOrder(): void
    {
        $firedEvents = [];
        $this->dispatcher->addListener(CreateEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'create';
        });
        $this->dispatcher->addListener(SyncEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'sync';
        });
        $this->dispatcher->addListener(PostSyncEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'post_sync';
        });

        $dto = new TestArticleDto();
        $dto->title = 'Event test';
        $this->processor->create($dto);

        self::assertSame(['create', 'sync', 'post_sync'], $firedEvents);
    }

    public function testUpdateSyncsNewTitle(): void
    {
        // First create
        $dto = new TestArticleDto();
        $dto->title = 'Original';
        /** @var TestArticle $entity */
        $entity = $this->processor->create($dto);

        // Capture ID before update — sync() will copy DTO's null id onto the entity
        $id = $entity->getId();
        self::assertNotNull($id);

        // Now update
        $dto->title = 'Updated';
        $this->processor->update($dto, $entity);

        $this->em->clear();
        $found = $this->em->find(TestArticle::class, $id);

        self::assertNotNull($found);
        self::assertSame('Updated', $found->title);
    }

    public function testUpdateFiresEventsInOrder(): void
    {
        $dto = new TestArticleDto();
        $dto->title = 'Original';
        $entity = $this->processor->create($dto);

        $firedEvents = [];
        $this->dispatcher->addListener(UpdateEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'update';
        });
        $this->dispatcher->addListener(SyncEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'sync';
        });
        $this->dispatcher->addListener(PostSyncEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'post_sync';
        });

        $dto->title = 'Updated';
        $this->processor->update($dto, $entity);

        self::assertSame(['update', 'sync', 'post_sync'], $firedEvents);
    }

    public function testDeleteHardRemovesEntityFromDb(): void
    {
        $dto = new TestArticleDto();
        $dto->title = 'To delete';
        /** @var TestArticle $entity */
        $entity = $this->processor->create($dto);
        $id = $entity->getId();

        $firedEvents = [];
        $this->dispatcher->addListener(DeleteEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'delete';
        });
        $this->dispatcher->addListener(SyncEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'sync';
        });
        $this->dispatcher->addListener(PostSyncEvent::class, function () use (&$firedEvents): void {
            $firedEvents[] = 'post_sync';
        });

        $this->processor->delete($dto, $entity);

        $this->em->clear();
        $found = $this->em->find(TestArticle::class, $id);
        self::assertNull($found);
        self::assertSame(['delete', 'sync', 'post_sync'], $firedEvents);
    }

    public function testDeleteSoftCallsDeleteMethodWithoutRemove(): void
    {
        // Persist a soft-delete article directly (CrudProcessor::create uses TestArticle class)
        $softArticle = new TestSoftDeleteArticle();
        $softArticle->title = 'Soft delete me';
        $this->em->persist($softArticle);
        $this->em->flush();
        $id = $softArticle->getId();

        // Use a minimal DTO pointing to TestSoftDeleteArticle
        $dto = new class implements \ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface {
            public function getId(): ?\Symfony\Component\Uid\Uuid
            {
                return null;
            }

            public function getEntityClass(): string
            {
                return TestSoftDeleteArticle::class;
            }
        };

        $removed = false;
        // Override remove to detect it is NOT called
        // We can verify via DB: entity should still exist but deleted=true
        $this->processor->delete($dto, $softArticle);

        $this->em->clear();
        $found = $this->em->find(TestSoftDeleteArticle::class, $id);

        self::assertNotNull($found, 'Soft-deleted entity must still exist in DB');
        self::assertTrue($found->isDeleted(), 'isDeleted() must return true after soft delete');
    }

    public function testToggleFlipsEnabledFlag(): void
    {
        $article = new TestArticle();
        $article->title = 'Toggle me';
        $this->em->persist($article);
        $this->em->flush();
        $id = $article->getId();

        self::assertTrue($article->isEnabled());

        $this->processor->toggle($article);

        $this->em->clear();
        $found = $this->em->find(TestArticle::class, $id);
        self::assertFalse($found->isEnabled());
    }

    public function testCreateRollsBackOnException(): void
    {
        $this->dispatcher->addListener(CreateEvent::class, function (): void {
            throw new \RuntimeException('Listener failed');
        });

        $dto = new TestArticleDto();
        $dto->title = 'Rollback test';

        try {
            $this->processor->create($dto);
            self::fail('Exception expected');
        } catch (\RuntimeException $e) {
            self::assertSame('Listener failed', $e->getMessage());
        }

        // DB must be empty — transaction was rolled back
        $result = $this->em->getRepository(TestArticle::class)->findAll();
        self::assertCount(0, $result);
    }
}
