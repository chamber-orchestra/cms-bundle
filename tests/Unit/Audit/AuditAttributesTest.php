<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Audit;

use ChamberOrchestra\CmsBundle\Audit\Attribute\Auditable;
use ChamberOrchestra\CmsBundle\Audit\Attribute\AuditIgnore;
use ChamberOrchestra\CmsBundle\Audit\Attribute\AuditTransform;
use ChamberOrchestra\CmsBundle\Audit\AuditMetadata;
use ChamberOrchestra\CmsBundle\Audit\AuditSigner;
use ChamberOrchestra\CmsBundle\Audit\AuditTransformer;
use PHPUnit\Framework\TestCase;

#[Auditable]
class Composition
{
    public string $title = '';
    #[AuditIgnore]
    public int $tempo = 0;
    #[AuditTransform(AuditTransform::HIDDEN)]
    public string $manuscriptKey = '';
}

final class Symphony extends Composition
{
    #[AuditTransform(AuditTransform::KEYED_HASH)]
    public string $conductor = '';
}

#[Auditable(actions: [Auditable::UPDATE, Auditable::DELETE])]
final class Rehearsal
{
}

final class Intermission
{
    public string $note = '';
}

/**
 * What the attributes of the journal say (`#[Auditable]`, `#[AuditIgnore]`, `#[AuditTransform]`), how a value is transformed and how a line is sealed.
 */
final class AuditAttributesTest extends TestCase
{
    public function testOnlyAClassWithTheAttributeIsJournalledAndItsChildrenToo(): void
    {
        $metadata = new AuditMetadata();

        $composition = $metadata->of(Composition::class);
        $rehearsal = $metadata->of(Rehearsal::class);

        self::assertNotNull($composition);
        self::assertNotNull($rehearsal);
        self::assertNotNull($metadata->of(new Symphony()), 'the attribute of a parent counts');
        self::assertNull($metadata->of(Intermission::class), 'no attribute, nothing is written');
        self::assertSame(Auditable::ACTIONS, $composition['actions']);
        self::assertSame([Auditable::UPDATE, Auditable::DELETE], $rehearsal['actions']);
    }

    public function testThePropertiesAreReadFromTheClassAndItsParents(): void
    {
        $meta = new AuditMetadata()->of(Symphony::class);

        self::assertNotNull($meta);
        self::assertSame(['tempo' => true], $meta['ignored']);
        self::assertEqualsCanonicalizing(['manuscriptKey' => AuditTransform::HIDDEN, 'conductor' => AuditTransform::KEYED_HASH], $meta['transforms']);
    }

    public function testAnUnknownActionOrTransformationIsRefusedAtOnce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Auditable(actions: ['purge']);
    }

    public function testAnUnknownTransformationIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuditTransform('rot13');
    }

    public function testAValueIsMaskedHashedOrKeyedHashed(): void
    {
        $plain = new AuditTransformer('secret-one');
        $other = new AuditTransformer('secret-two');

        self::assertSame('***', $plain->apply(AuditTransform::HIDDEN, 'anything'));
        self::assertSame('***', $plain->apply(AuditTransform::HIDDEN, null), 'that a value was there is not given away, nor that it was not');

        $hash = $plain->apply(AuditTransform::HASH, '+7 495 792-9000');
        self::assertIsString($hash);
        self::assertMatchesRegularExpression('/^sha256:[0-9a-f]{16}$/', $hash);
        self::assertSame($hash, $other->apply(AuditTransform::HASH, '+7 495 792-9000'), 'a plain digest is the same everywhere: two lines about one value can be compared');
        self::assertNull($plain->apply(AuditTransform::HASH, null));

        $keyed = $plain->apply(AuditTransform::KEYED_HASH, 'ivan@example.ru');
        self::assertIsString($keyed);
        self::assertMatchesRegularExpression('/^hmac:[0-9a-f]{16}$/', $keyed);
        self::assertSame($keyed, $plain->apply(AuditTransform::KEYED_HASH, 'ivan@example.ru'));
        self::assertNotSame($keyed, $other->apply(AuditTransform::KEYED_HASH, 'ivan@example.ru'), 'with another secret it is another digest: the value cannot be guessed from it');
    }

    public function testALineIsSealedAndAChangedLineDoesNotFitItsSeal(): void
    {
        $signer = new AuditSigner(true, 'seal-secret');
        $changes = ['title' => ['Old', 'New'], 'tempo' => [1, 2]];
        $seal = $signer->sign('id-1', '2026-11-24 12:00:00', 'chief@example.ru', 'update', 'App\\Composition', '7', 'Symphony', $changes, '10.0.0.0');

        self::assertIsString($seal);
        self::assertTrue($signer->verify($seal, 'id-1', '2026-11-24 12:00:00', 'chief@example.ru', 'update', 'App\\Composition', '7', 'Symphony', ['tempo' => [1, 2], 'title' => ['Old', 'New']], '10.0.0.0'), 'the database keeps the keys of an object in an order of its own');
        foreach ([
            ['chief@example.ru', 'update', 'Symphony', ['title' => ['Old', 'Newer'], 'tempo' => [1, 2]], '10.0.0.0'],
            ['other@example.ru', 'update', 'Symphony', $changes, '10.0.0.0'],
            ['chief@example.ru', 'delete', 'Symphony', $changes, '10.0.0.0'],
            ['chief@example.ru', 'update', 'Sonata', $changes, '10.0.0.0'],
            ['chief@example.ru', 'update', 'Symphony', $changes, '10.9.9.0'],
        ] as [$actor, $action, $label, $with, $ip]) {
            self::assertFalse($signer->verify($seal, 'id-1', '2026-11-24 12:00:00', $actor, $action, 'App\\Composition', '7', $label, $with, $ip), $actor.$action.$label.$ip);
        }
        self::assertFalse(new AuditSigner(true, 'another-secret')->verify($seal, 'id-1', '2026-11-24 12:00:00', 'chief@example.ru', 'update', 'App\\Composition', '7', 'Symphony', $changes, '10.0.0.0'), 'a line sealed with another secret is not ours');
    }

    public function testWithoutSealsNothingIsSealedAndNothingFits(): void
    {
        $signer = new AuditSigner(false, '');

        self::assertFalse($signer->enabled());
        self::assertNull($signer->sign('id', '2026-11-24 12:00:00', 'a', 'create', 'C', '1', 'L', null, null));
        self::assertFalse($signer->verify('anything', 'id', '2026-11-24 12:00:00', 'a', 'create', 'C', '1', 'L', null, null));
    }
}
