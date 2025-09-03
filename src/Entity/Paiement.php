<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $statut = 'effectué'; // Statut par défaut

    #[ORM\Column(type: 'float')]
    private float $montant = 0.0; // Le montant de CE paiement, saisi par l'utilisateur

    #[ORM\ManyToOne(targetEntity: ArretDeCaisse::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: true)] // Un paiement n'a pas d'arrêt de caisse tant qu'il n'est pas clôturé
    private ?ArretDeCaisse $arretDeCaisse = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;
    
    public function getId(): ?int { return $this->id; }
    public function getDatePaiement(): ?\DateTimeInterface { return $this->datePaiement; }
    public function setDatePaiement(\DateTimeInterface $datePaiement): static { $this->datePaiement = $datePaiement; return $this; }
    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $commande): static { $this->commande = $commande; return $this; }
    public function getMontant(): float { return $this->montant; }
    public function setMontant(float $montant): static { $this->montant = $montant; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getArretDeCaisse(): ?ArretDeCaisse
    {
        return $this->arretDeCaisse;
    }

    public function setArretDeCaisse(?ArretDeCaisse $arretDeCaisse): static
    {
        $this->arretDeCaisse = $arretDeCaisse;
        return $this;
    }
}