<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Command;

use ChamberOrchestra\CmsBundle\Security\Entity\AbstractAdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * `cms:admin:create <email>`: the first administrator (the others are made in the admin). The password is asked for (hidden) unless it is given with `--password`.
 */
#[AsCommand(name: 'cms:admin:create', description: 'Creates an administrator of the admin (ROLE_SUPER_ADMIN)')]
final class CreateAdminCommand
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%chamber_orchestra_cms.admin_user_class%')]
        private readonly string $class,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail: the sign-in name')]
        string $email,
        #[Option('Full name')]
        ?string $name = null,
        #[Option('Password (asked for when it is not given)')]
        ?string $password = null,
    ): int {
        $email = \mb_strtolower(\trim($email));
        if (false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Not an e-mail address.');

            return Command::INVALID;
        }
        if (null !== $this->em->getRepository($this->class)->findOneBy(['email' => $email])) {
            $io->error(\sprintf('An administrator with %s exists.', $email));

            return Command::FAILURE;
        }
        if (null === $password) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $password = $io->askQuestion($question);
        }
        if (!\is_string($password) || \mb_strlen($password) < 8) {
            $io->error('The password must have at least 8 characters.');

            return Command::INVALID;
        }

        /** @var AbstractAdminUser $admin */
        $admin = new ($this->class)($email, '', $name);
        $admin->changePassword($this->hasher->hashPassword($admin, $password));
        $this->em->persist($admin);
        $this->em->flush();
        $io->success(\sprintf('Administrator %s created.', $email));

        return Command::SUCCESS;
    }
}
