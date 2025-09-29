<?php
namespace App\Entity;

use App\Repository\DevisLigneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisLigneRepository::class)]
class DevisLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Devis::class, inversedBy: "lignes")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    #[ORM\Column(length: 255)]
    private ?string $descriptionProduit = null;

    #[ORM\Column(type:"integer")]
    private int $quantite;

    #[ORM\Column(type:"float")]
    private float $prixUnitaire;

    #[ORM\Column(type:"float")]
    private float $prixTotal;

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }
    public function getDevis(): ?Devis { return $this->devis; }
    public function setDevis(?Devis $devis): self { $this->devis = $devis; return $this; }
    public function getDescriptionProduit(): ?string { return $this->descriptionProduit; }
    public function setDescriptionProduit(string $descriptionProduit): static { $this->descriptionProduit = $descriptionProduit; return $this; }
    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $quantite): self { $this->quantite = $quantite; return $this; }
    public function getPrixUnitaire(): float { return $this->prixUnitaire; }
    public function setPrixUnitaire(float $prixUnitaire): self { $this->prixUnitaire = $prixUnitaire; return $this; }
    public function getPrixTotal(): float { return $this->prixTotal; }
    public function setPrixTotal(float $prixTotal): self { $this->prixTotal = $prixTotal; return $this; }

    public function __toString(): string
    {
        $prix = $this->getPrixUnitaire();

        // Si le prix est un entier (pas de décimales), on enlève .00
        if (fmod($prix, 1) == 0.0) {
            $prixFormat = number_format($prix, 0, '', '');
        } else {
            $prixFormat = number_format($prix, 2, '.', '');
        }

        return sprintf(
            "%s (x%d, %s MGA)",
            $this->getDescriptionProduit() ?? 'N/A',
            $this->getQuantite(),
            $prixFormat
        );
    }
}
