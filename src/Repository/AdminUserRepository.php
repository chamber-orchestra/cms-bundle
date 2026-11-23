<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Repository;

use ChamberOrchestra\CmsBundle\Security\Entity\AbstractAdminUser;
use ChamberOrchestra\CmsBundle\Security\Entity\AdminUser;
use ChamberOrchestra\DoctrineExtensionsBundle\Repository\ServiceEntityRepository;
use ChamberOrchestra\PaginationBundle\PaginationAwareTrait;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * The repository is a service (register it in the application): the admin's list pages through it.
 */
class AdminUserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    use PaginationAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUser::class);
    }

    /** A successful sign-in with an old hash (the MD5 of the old site) stores a modern one. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof AbstractAdminUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->changePassword($newHashedPassword);
        $this->getEntityManager()->persist($user); // the change tracking is explicit: what is not persisted is not saved
        $this->getEntityManager()->flush();
    }
}
