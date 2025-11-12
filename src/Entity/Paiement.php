<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
class Paiement
{
    public const STATUT_EFFECTUE = 'effectué';
    public const STATUT_EN_ATTENTE = 'en attente'; // Pour un paiement qui aurait dû être fait mais ne l'est pas encore
    public const STATUT_A_VENIR = 'à venir';     // Pour un paiement futur prévu (ex: le solde)
    public const STATUT_ANNULE = 'annulé';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: 'string', length: 50)]
    // On change le statut par défaut pour être plus prudent.
    // Un paiement est 'en attente' jusqu'à ce qu'on confirme qu'il est fait.
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: 'float')]
    private float $montant = 0.0; // Le montant de CE paiement, saisi par l'utilisateur

    #[ORM\ManyToOne(targetEntity: ArretDeCaisse::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(onDelete: "SET NULL", nullable: true)]
    private ?ArretDeCaisse $arretDeCaisse = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Commande $commande = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $referencePaiement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $detailsPaiement = null;

        public function __construct()
    {
        // On initialise la date de paiement dès la création de l'objet.
        // C'est une bonne pratique qui évite les valeurs nulles inattendues.
        $this->datePaiement = new \DateTime();
    }
    
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

    public function getReferencePaiement(): ?string { return $this->referencePaiement; }
    public function setReferencePaiement(?string $referencePaiement): self { $this->referencePaiement = $referencePaiement; return $this; }

    public function getDetailsPaiement(): ?string
    {
        return $this->detailsPaiement;
    }

    public function setDetailsPaiement(?string $detailsPaiement): static
    {
        $this->detailsPaiement = $detailsPaiement;
        return $this;
    }

    public function __toString(): string
    {
        // On utilise getReferencePaiement() au lieu de getMoyenPaiement()
        // On vérifie aussi que la valeur n'est pas vide
        if ($this->getMontant() === 0.0 && empty($this->getReferencePaiement())) {
            return 'Nouveau Paiement'; // Label pour un paiement pas encore rempli
        }

        // On formate le montant pour une meilleure lisibilité
        $montantFormatted = number_format($this->getMontant(), 0, ',', ' ');

        return sprintf(
            'Paiement de %s MGA (%s)',
            $montantFormatted,
            $this->getReferencePaiement() 
        );
    }
}