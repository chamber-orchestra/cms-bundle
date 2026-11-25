<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit\Attribute;

/**
 * Puts an entity into the journal: what an administrator creates, changes, deletes or restores in it is written (who, when, which row, what changed). Nothing is written for a class
 * without it. The attribute can sit on a base class: the entities that extend it are journalled too.
 *
 *     #[ORM\Entity]
 *     #[Auditable]                                   // every action
 *     class Product {}
 *
 *     #[Auditable(actions: [Auditable::UPDATE, Auditable::DELETE])]   // only these
 *     class Banner {}
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Auditable
{
    public const string CREATE = 'create';
    public const string UPDATE = 'update';
    public const string DELETE = 'delete';
    public const string RESTORE = 'restore';
    public const array ACTIONS = [self::CREATE, self::UPDATE, self::DELETE, self::RESTORE];

    /**
     * @param list<string> $actions which of the actions are written (all by default)
     */
    public function __construct(public array $actions = self::ACTIONS)
    {
        foreach ($actions as $action) {
            if (!\in_array($action, self::ACTIONS, true)) {
                throw new \InvalidArgumentException(\sprintf('Unknown audit action "%s": one of %s.', $action, \implode(', ', self::ACTIONS)));
            }
        }
    }
}
