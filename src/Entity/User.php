<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use function in_array;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank, Assert\Email]
    private string $email;

    /**
     * Opslag van gehashte pincode. We gebruiken het standaard password-hashing mechanisme.
     */
    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastFailedAt = null;

    public function getId(): ?int { return $id ?? null; }

    public function getUserIdentifier(): string { return $this->email; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    /** @return string[] */
    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_values(array_unique($roles));
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    /** Gehashte pincode */
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hash): self { $this->password = $hash; return $this; }

    public function eraseCredentials(): void {}

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $active): self { $this->isActive = $active; return $this; }

    public function getFailedLoginAttempts(): int { return $this->failedLoginAttempts; }
    public function setFailedLoginAttempts(int $i): self { $this->failedLoginAttempts = $i; return $this; }

    public function getLastFailedAt(): ?DateTimeImmutable { return $this->lastFailedAt; }
    public function setLastFailedAt(?DateTimeImmutable $d): self { $this->lastFailedAt = $d; return $this; }
}
