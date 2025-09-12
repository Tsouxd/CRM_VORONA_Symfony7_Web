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
    public function addLigne(FactureLigne $ligne): self { $this->lignes->add($ligne); $ligne->setFacture($this); return $this; }
    public function removeLigne(FactureLigne $ligne): self { $this->lignes->removeElement($ligne); return $this; }
    public function getTotal(): float { return $this->total; }
    public function setTotal(float $total): self { $this->total = $total; return $this; }
    public function getFraisLivraison(): float { return $this->fraisLivraison; }
    public function setFraisLivraison(float $fraisLivraison): self { $this->fraisLivraison = $fraisLivraison; return $this; }
    public function getLivreur(): ?string { return $this->livreur; }
    public function setLivreur(?string $livreur): self { $this->livreur = $livreur; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
}
