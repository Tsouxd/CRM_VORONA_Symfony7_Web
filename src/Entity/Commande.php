<?php

// src/Entity/Commande.php
namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(type: "boolean")]
    private bool $isFacture = false;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    private ?Client $client = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $statut = 'en attente'; // valeur par défaut

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeProduit::class, cascade: ['persist'])]
    private Collection $commandeProduits;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Paiement::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $paiements;

    public function __construct()
    {
        $this->commandeProduits = new ArrayCollection();
        $this->paiements = new ArrayCollection();
    }

    // getters / setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCommande(): ?\DateTime
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTime $dateCommande): static
    {
        $this->dateCommande = $dateCommande;

        return $this;
    }

    public function isFacture(): ?bool
    {
        return $this->isFacture;
    }

    public function setIsFacture(bool $isFacture): static
    {
        $this->isFacture = $isFacture;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Collection<int, CommandeProduit>
     */
    public function getCommandeProduits(): Collection
    {
        return $this->commandeProduits;
    }

    public function addCommandeProduit(CommandeProduit $commandeProduit): static
    {
        if (!$this->commandeProduits->contains($commandeProduit)) {
            $this->commandeProduits->add($commandeProduit);
            $commandeProduit->setCommande($this);
        }

        return $this;
    }

    public function removeCommandeProduit(CommandeProduit $commandeProduit): static
    {
        if ($this->commandeProduits->removeElement($commandeProduit)) {
            // set the owning side to null (unless already changed)
            if ($commandeProduit->getCommande() === $this) {
                $commandeProduit->setCommande(null);
            }
        }

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getTotal(): float
    {
        // Ne pas inclure si la commande est annulée
        if ($this->getStatut() === 'annulée' or $this->getStatut() === 'en attente') {
            return 0.0;
        }

        // Ne pas inclure si un des paiements est annulé
        foreach ($this->getPaiements() as $paiement) {
            if ($paiement->getStatut() === 'annulée' or $paiement->getStatut() === 'en attente') {
                return 0.0;
            }
        }

        $total = 0.0;

        foreach ($this->getCommandeProduits() as $commandeProduit) {
            $produit = $commandeProduit->getProduit();
            if ($produit !== null) {
                $prix = $produit->getPrix(); // prix en centimes
                $quantite = $commandeProduit->getQuantite();
                $total += ($prix * $quantite);
            }
        }

        return $total / 100; // conversion en euros
    }

    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): self
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements[] = $paiement;
            $paiement->setCommande($this);
        }

        return $this;
    }

    public function removePaiement(Paiement $paiement): self
    {
        if ($this->paiements->removeElement($paiement)) {
            if ($paiement->getCommande() === $this) {
                $paiement->setCommande(null);
            }
        }

        return $this;
    }
    
    public function __toString(): string
    {
        return 'Commande n°' . $this->getId(); // ou autre champ significatif
    }    
}
