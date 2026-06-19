<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocsUserRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A single internal credential that gates access to the OpenAPI / Swagger UI.
 * Authenticated via HTTP Basic on its own firewall and provider — entirely
 * separate from the JWT-authenticated API users. Never exposed by the API.
 */
#[ExclusionPolicy(policy: 'all')]
#[ORM\Entity(repositoryClass: DocsUserRepository::class)]
#[ORM\Table(name: 'docs_users')]
class DocsUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_DOCS = 'ROLE_DOCS';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $username;

    #[ORM\Column]
    private string $password;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return [self::ROLE_DOCS];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
