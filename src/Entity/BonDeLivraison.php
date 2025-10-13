<?php
// src/Entity/BonDeLivraison.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class BonDeLivraison
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'bonsDeLivraison')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\OneToMany(mappedBy: 'bonDeLivraison', targetEntity: BonDeLivraisonLigne::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct(Commande $commande)
    {
        $this->commande = $commande;
        $this->dateCreation = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }
    
    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    public function getCommande(): ?Commande { return $this->commande; }
    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function getLignes(): Collection { return $this->lignes; }
    public function addLigne(BonDeLivraisonLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setBonDeLivraison($this);
        }
        return $this;
    }
}