<?php
namespace App\Entity;

use App\Repository\ArretDeCaisseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArretDeCaisseRepository::class)]
class ArretDeCaisse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateCloture;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $utilisateur;

    #[ORM\Column(type: 'float')]
    private float $fondDeCaisseInitial = 0.0;

    #[ORM\Column(type: 'json')] // Stocke tous les détails (théorique, compté, écart)
    private array $detailsPaiements = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'arretDeCaisse', targetEntity: Paiement::class)]
    private Collection $paiements;

    public function __construct(User $user)
    {
        $this->dateCloture = new \DateTime();
        $this->utilisateur = $user;
        $this->paiements = new ArrayCollection();
    }
    public function getId(): ?int { return $this->id; }
    public function getDateCloture(): \DateTimeInterface { return $this->dateCloture; }
    public function setDateCloture(\DateTimeInterface $dateCloture): static { $this->dateCloture = $dateCloture; return $this; }
    public function getUtilisateur(): User { return $this->utilisateur; }
    public function setUtilisateur(User $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getFondDeCaisseInitial(): float { return $this->fondDeCaisseInitial; }
    public function setFondDeCaisseInitial(float $fondDeCaisseInitial): static { $this->fondDeCaisseInitial = $fondDeCaisseInitial; return $this; }
    public function getDetailsPaiements(): array { return $this->detailsPaiements; }
    public function setDetailsPaiements(array $detailsPaiements): static { $this->detailsPaiements = $detailsPaiements; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    /** @return Collection<int, Paiement> */
    public function getPaiements(): Collection { return $this->paiements; }
    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setArretDeCaisse($this); // Assure la cohérence des deux côtés
        }
        return $this;
    }
}