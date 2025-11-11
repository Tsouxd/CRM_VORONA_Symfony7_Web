<?php
// src/Entity/Tache.php

namespace App\Entity;

use App\Repository\TacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TacheRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Tache
{
    public const STATUT_A_FAIRE = 'À faire';
    public const STATUT_EN_COURS = 'En cours';
    public const STATUT_TERMINE = 'Terminé';
    public const STATUT_BLOQUE = 'Bloqué';

    public const PRIORITE_FAIBLE = 'Faible';
    public const PRIORITE_NORMALE = 'Normale';
    public const PRIORITE_HAUTE = 'Haute';
    public const PRIORITE_URGENTE = 'Urgente';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = self::STATUT_A_FAIRE;

    #[ORM\Column(length: 50)]
    private ?string $priorite = self::PRIORITE_NORMALE;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tachesAssignees')]
    private ?User $assigneA = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tachesCrees')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creePar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->titre ?? 'Nouvelle tâche';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(string $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeInterface $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;

        return $this;
    }

    public function getAssigneA(): ?User
    {
        return $this->assigneA;
    }

    public function setAssigneA(?User $assigneA): static
    {
        $this->assigneA = $assigneA;

        return $this;
    }

    public function getCreePar(): ?User
    {
        return $this->creePar;
    }

    public function setCreePar(?User $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getJoursRestants(): string
    {
        if (!$this->dateEcheance) {
            return '-';
        }

        $now = new \DateTimeImmutable();
        $interval = $this->dateEcheance->diff($now);
        $jours = (int) $interval->format('%r%a'); // jours restants : positif si futur, négatif si dépassé

        if ($jours < 0) {
            return 'Expiré';
        }

        if ($jours === 0) {
            return 'Date limite atteinte';
        }

        return $jours . ' jour' . ($jours > 1 ? 's' : '');
    }
}