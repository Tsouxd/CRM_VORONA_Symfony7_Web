<?php

// src/Entity/Paiement.php
namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $datePaiement;

    #[ORM\Column(type: 'string', length: 50)]
    private string $statut; // exemple : "en attente", "effectué", "annulé"

    #[ORM\Column(type: 'float')]
    private float $montant = 0.0; // initialisé à 0 par défaut

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatePaiement(): ?\DateTime
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(\DateTime $datePaiement): static
    {
        $this->datePaiement = $datePaiement;

        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        // Calcul automatique du montant depuis la commande liée
        if ($commande !== null) {
            $this->montant = $commande->getTotal(); // 👈 Auto-calcul ici
        }

        return $this;
    }

    public function getMontant(): float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function updateMontant(): void
    {
        if ($this->commande) {
            $this->montant = $this->commande->getTotal(); // getTotal() est la somme des produits * quantité
        }
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

    public function __toString(): string
    {
        return $this->statut;
    }
}