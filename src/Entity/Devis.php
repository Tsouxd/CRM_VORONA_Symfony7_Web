<?php
namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisRepository::class)]
class Devis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\OneToMany(mappedBy: "devis", targetEntity: DevisLigne::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $lignes;

    #[ORM\Column(type:"float")]
    private float $total = 0;

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
    public function addLigne(DevisLigne $ligne): self { $this->lignes->add($ligne); $ligne->setDevis($this); return $this; }
    public function removeLigne(DevisLigne $ligne): self { $this->lignes->removeElement($ligne); return $this; }
    public function getTotal(): float { return $this->total; }
    public function setTotal(float $total): self { $this->total = $total; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
}
