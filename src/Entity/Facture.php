<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\OneToMany(mappedBy: "facture", targetEntity: FactureLigne::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $lignes;

    #[ORM\Column(type:"float")]
    private float $total = 0;

    #[ORM\Column(type:"float")]
    private float $fraisLivraison = 0;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private ?string $livreur = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modeDePaiement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $detailsPaiement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $methodePaiement = null;

    #[ORM\Column(type:"float", nullable:true)]
    private ?float $remise = null;

    #[ORM\Column(type:"float", nullable:true)]
    private ?float $acompte = null;

    // ✅ RELATION DIRECTE VERS COMMANDE (au lieu du numéroBonCommande)
    #[ORM\OneToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Commande $commande = null;
    
    #[ORM\Column(type:"datetime")]
    private \DateTimeInterface $dateCreation;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): self { $this->client = $client; return $this; }

    public function getLignes(): Collection { return $this->lignes; }
    public function addLigne(FactureLigne $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setFacture($this);
        }
        return $this;
    }
    public function removeLigne(FactureLigne $ligne): self
    {
        $this->lignes->removeElement($ligne);
        return $this;
    }

    public function getTotal(): float { return $this->total; }
    public function setTotal(float $total): self { $this->total = $total; return $this; }

    public function getFraisLivraison(): float { return $this->fraisLivraison; }
    public function setFraisLivraison(float $fraisLivraison): self { $this->fraisLivraison = $fraisLivraison; return $this; }

    public function getLivreur(): ?string { return $this->livreur; }
    public function setLivreur(?string $livreur): self { $this->livreur = $livreur; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    
    public function getModeDePaiement(): ?string { return $this->modeDePaiement; }
    public function setModeDePaiement(?string $modeDePaiement): static { $this->modeDePaiement = $modeDePaiement; return $this; }

    public function getRemise(): ?float { return $this->remise; }
    public function setRemise(?float $remise): self { $this->remise = $remise; return $this; }

    public function getAcompte(): ?float { return $this->acompte; }
    public function setAcompte(?float $acompte): self { $this->acompte = $acompte; return $this; }

    public function getDetailsPaiement(): ?string { return $this->detailsPaiement; }
    public function setDetailsPaiement(?string $detailsPaiement): static { $this->detailsPaiement = $detailsPaiement; return $this; }

    public function getMethodePaiement(): ?string { return $this->methodePaiement; }
    public function setMethodePaiement(?string $methodePaiement): static { $this->methodePaiement = $methodePaiement; return $this; }

    // ✅ Relation vers la commande
    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $commande): self { $this->commande = $commande; return $this; }

    // ✅ Optionnel : pour afficher plus clairement dans EasyAdmin ou debug
    public function __toString(): string
    {
        return 'Facture #' . $this->id . ' (Commande ' . ($this->commande?->getId() ?? 'N/A') . ')';
    }
}