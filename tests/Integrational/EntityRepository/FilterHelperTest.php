<?php

declare(strict_types=1);

namespace Tests\Integrational\EntityRepository;

use ChamberOrchestra\CmsBundle\EntityRepository\Helper\FilterHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Integrational\Entity\TestArticle;

final class FilterHelperTest extends KernelTestCase
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

    public function testStringValueProducesLikeClause(): void
    {
        $qb = $this->makeQb();
        $helper = new FilterHelper($qb);
        $helper(['title' => 'hello'], []);

        $dql = $qb->getDQL();
        self::assertStringContainsString('LOWER(r.title)', $dql);
        self::assertStringContainsString('LIKE', $dql);

        $params = $qb->getParameters();
        self::assertCount(1, $params);
        self::assertSame('%hello%', $params->first()->getValue());
    }

    public function testIntegerValueProducesEqClause(): void
    {
        $qb = $this->makeQb();
        $helper = new FilterHelper($qb);
        $helper(['id' => 5], []);

        $dql = $qb->getDQL();
        self::assertStringContainsString('r.id', $dql);
        // eq() produces = :param
        self::assertMatchesRegularExpression('/r\.id\s*=\s*:/', $dql);
    }

    public function testBoolValueProducesEqClause(): void
    {
        $qb = $this->makeQb();
        $helper = new FilterHelper($qb);
        $helper(['enabled' => true], []);

        $dql = $qb->getDQL();
        self::assertStringContainsString('r.enabled', $dql);
        self::assertMatchesRegularExpression('/r\.enabled\s*=\s*:/', $dql);
    }

    public function testArrayValueProducesInClause(): void
    {
        $qb = $this->makeQb();
        $helper = new FilterHelper($qb);
        $helper(['id' => [1, 2, 3]], []);

        $dql = $qb->getDQL();
        self::assertStringContainsString('IN', $dql);
        self::assertStringContainsString('r.id', $dql);
    }

    public function testNullValueSkipsField(): void
    {
        $qb = $this->makeQb();
        $helper = new FilterHelper($qb);
        $helper(['title' => null], []);

        $dql = $qb->getDQL();
        self::assertStringNotContainsString('WHERE', $dql);
    }

    public function testCallableMappingPassesQbToCallable(): void
    {
        $qb = $this->makeQb();
        $helper = new FilterHelper($qb);
        $called = false;

        $helper(
            ['title' => 'value'],
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
        $helper = new FilterHelper($qb);
        $helper(['name' => 'test'], ['name' => 'title']);

        $dql = $qb->getDQL();
        self::assertStringContainsString('r.title', $dql);
        self::assertStringNotContainsString('r.name', $dql);
    }
}
