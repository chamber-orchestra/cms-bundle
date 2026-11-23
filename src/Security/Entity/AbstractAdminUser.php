<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Security\Entity;

use ChamberOrchestra\DoctrineClockBundle\Entity\TimestampCreateTrait;
use ChamberOrchestra\DoctrineExtensionsBundle\Entity\GeneratedIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who signs in to the admin: the common part of an administrator. The bundle ships it as the concrete {@see AdminUser}; an application that needs more fields
 * extends this class (an entity of its own, `#[ORM\Entity(repositoryClass: AdminUserRepository::class)]`) and names it in the provider of its security
 * configuration. The administrators are not the customers of the site: the admin firewall has its own provider and its own session.
 */
#[ORM\MappedSuperclass]
abstract class AbstractAdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    use GeneratedIdTrait;
    use TimestampCreateTrait;
    /** The only role the bundle ships: the administrator who may do everything. An application adds its own roles (an editor, a manager) next to it. */
    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /** Every administrator has it: it is what the admin firewall asks for (`access_control`). */
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Column(type: Types::STRING, length: 180)]
    protected string $email;

    /** A hash (the hasher of the admin firewall; an old hash is rehashed at the next sign-in). */
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $password;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    protected ?string $fullName = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    protected array $roles = [self::ROLE_SUPER_ADMIN];

    /** A disabled administrator cannot sign in (see `Security\AdminUserChecker`). */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    protected bool $enabled = true;

    #[ORM\Column(type: DatePointType::NAME, nullable: true)]
    protected ?DatePoint $lastLoginAt = null;

    /**
     * @param list<string> $roles
     */
    public function __construct(string $email, string $password = '', ?string $fullName = null, array $roles = [self::ROLE_SUPER_ADMIN])
    {
        $this->roles = $roles;
        $this->email = \mb_strtolower(\trim($email));
        $this->password = $password;
        $this->fullName = $fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    /** What the admin shows as the name of the signed-in administrator. */
    public function getSystemName(): string
    {
        return null !== $this->fullName && '' !== $this->fullName ? $this->fullName : $this->email;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLastLoginAt(): ?DatePoint
    {
        return $this->lastLoginAt;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function changePassword(string $hash): void
    {
        $this->password = $hash;
    }

    public function rename(?string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function enable(bool $enabled = true): void
    {
        $this->enabled = $enabled;
    }

    public function signedIn(DatePoint $at): void
    {
        $this->lastLoginAt = $at;
    }

    /**
     * The roles the administrator was given and ROLE_ADMIN.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return \array_values(\array_unique([self::ROLE_ADMIN, ...$this->roles]));
    }

    /**
     * @param list<string> $roles
     */
    public function giveRoles(array $roles): void
    {
        $this->roles = \array_values(\array_unique($roles));
    }

    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : throw new \LogicException('An administrator has no e-mail.');
    }

    /** The password changes the "remember me" signature and the session is refreshed with it: never serialise the hash itself. */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0*\0password"] = \hash('crc32c', $this->password);

        return $data;
    }
}
