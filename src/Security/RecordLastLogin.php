<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Security;

use ChamberOrchestra\CmsBundle\Security\Entity\AbstractAdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The time of the last sign-in of an administrator (shown in the list of administrators).
 */
final readonly class RecordLastLogin
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[AsEventListener]
    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof AbstractAdminUser) {
            $user->signedIn(new DatePoint());
            $this->em->persist($user);
            $this->em->flush();
        }
    }
}
