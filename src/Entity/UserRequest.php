<?php
// src/Entity/UserRequest.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class UserRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    // ✅ AJOUT DE LA NOUVELLE PROPRIÉTÉ
    #[ORM\Column(length: 50)]
    private string $status = 'pas encore fait'; // Valeur par défaut

    #[ORM\Column(length: 255, nullable: true)] // On le met nullable pour qu'il ne soit pas obligatoire
    private ?string $roleDemander = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    // ✅ AJOUT DES GETTER ET SETTER POUR LE STATUT
    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRoleDemander(): ?string
    {
        return $this->roleDemander;
    }

    public function setRoleDemander(?string $roleDemander): static
    {
        $this->roleDemander = $roleDemander;
        return $this;
    }
}