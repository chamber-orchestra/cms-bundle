<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Security\Entity;

use ChamberOrchestra\CmsBundle\Repository\AdminUserRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The administrator the bundle ships (table `admin_user`). Use it as it is, or extend {@see AbstractAdminUser} with an entity of your own.
 */
#[ORM\Entity(repositoryClass: AdminUserRepository::class)]
#[ORM\Table(name: 'admin_user')]
#[ORM\UniqueConstraint(name: 'admin_user_email_uniq', columns: ['email'])]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
#[ORM\ChangeTrackingPolicy(value: 'DEFERRED_EXPLICIT')]
class AdminUser extends AbstractAdminUser
{
}
