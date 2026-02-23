<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\EntityRepository;

use ChamberOrchestra\CmsBundle\EntityRepository\Helper\SortHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Integrational\Entity\TestArticle;

final class SortHelperTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function makeQb(): \Doctrine\ORM\QueryBuilder
    {
        return $this->em->getRepository(TestArticle::class)->createQueryBuilder('r');
    }

    public function testNormalFieldAscProducesOrderBy(): void
    {
        $qb = $this->makeQb();
        $helper = new SortHelper($qb);
        $helper(['title' => 'ASC'], []);

        $dql = $qb->getDQL();
        self::assertStringContainsString('ORDER BY r.title ASC', $dql);
    }

    public function testNullValueSkipsField(): void
    {
        $qb = $this->makeQb();
        $helper = new SortHelper($qb);
        $helper(['title' => null], []);

        $dql = $qb->getDQL();
        self::assertStringNotContainsString('ORDER BY', $dql);
    }

    public function testCallableMappingPassesQbToCallable(): void
    {
        $qb = $this->makeQb();
        $helper = new SortHelper($qb);
        $called = false;

        $helper(
            ['title' => 'ASC'],
            ['title' => function (\Doctrine\ORM\QueryBuilder $passedQb) use ($qb, &$called): void {
                $called = true;
                self::assertSame($qb, $passedQb);
            }]
        );

        self::assertTrue($called);
    }

    public function testStringMappingRenamesField(): void
    {
        $qb = $this->makeQb();
        $helper = new SortHelper($qb);
        $helper(['name' => 'DESC'], ['name' => 'title']);

        $dql = $qb->getDQL();
        self::assertStringContainsString('r.title DESC', $dql);
        self::assertStringNotContainsString('r.name', $dql);
    }
}
