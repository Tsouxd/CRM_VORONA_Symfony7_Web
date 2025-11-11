<?php
// src/Entity/BonDeLivraisonLigne.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class BonDeLivraisonLigne
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?BonDeLivraison $bonDeLivraison = null;

    #[ORM\Column(length: 255)]
    private ?string $descriptionProduit = null;

    #[ORM\Column]
    private ?int $quantite = 0;

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getBonDeLivraison(): ?BonDeLivraison { return $this->bonDeLivraison; }
    public function setBonDeLivraison(?BonDeLivraison $bonDeLivraison): static { $this->bonDeLivraison = $bonDeLivraison; return $this; }
    public function getDescriptionProduit(): ?string { return $this->descriptionProduit; }
    public function setDescriptionProduit(string $descriptionProduit): static { $this->descriptionProduit = $descriptionProduit; return $this; }
    public function getQuantite(): ?int { return $this->quantite; }
    public function setQuantite(int $quantite): static { $this->quantite = $quantite; return $this; }
}