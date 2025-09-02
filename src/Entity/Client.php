<?php

// src/Entity/Client.php
namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    public const TYPE_PARTICULIER = 'particulier';
    public const TYPE_PROFESSIONNEL = 'professionnel';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Commande::class)]
    private Collection $commandes;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_PARTICULIER;

    // --- Champs Particuliers ---
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adresseLivraison = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuLivraison = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $heureLivraison = null;

    // --- Champs Professionnels ---
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stat = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adresse = null;

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?string $adresseLivraison): static
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getLieuLivraison(): ?string
    {
        return $this->lieuLivraison;
    }

    public function setLieuLivraison(?string $lieuLivraison): static
    {
        $this->lieuLivraison = $lieuLivraison;
        return $this;
    }

    public function getHeureLivraison(): ?\DateTimeInterface
    {
        return $this->heureLivraison;
    }

    public function setHeureLivraison(?\DateTimeInterface $heureLivraison): static
    {
        $this->heureLivraison = $heureLivraison;
        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setClient($this);
        }
        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            if ($commande->getClient() === $this) {
                $commande->setClient(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom;
    }

    public function getNif(): ?string
    {
        return $this->nif;
    }

    public function setNif(?string $nif): static
    {
        $this->nif = $nif;
        return $this;
    }

    public function getStat(): ?string
    {
        return $this->stat;
    }

    public function setStat(?string $stat): static
    {
        $this->stat = $stat;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }
}