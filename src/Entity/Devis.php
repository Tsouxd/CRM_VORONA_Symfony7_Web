<?php
namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User; 

#[ORM\Entity(repositoryClass: DevisRepository::class)]
class Devis
{
    public const STATUT_ENVOYE = 'Envoyé';
    public const STATUT_BAT_PRODUCTION = 'BAT/Production';
    public const STATUT_RELANCE = 'Relance';
    public const STATUT_PERDU = 'Perdu';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\OneToMany(mappedBy: "devis", targetEntity: DevisLigne::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $lignes;

    #[ORM\Column(type:"float")]
    private float $total = 0;

    #[ORM\Column(type:"datetime")]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modeDePaiement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $detailsPaiement = null; // Pour la référence

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $methodePaiement = null; // Pour les conditions

    #[ORM\ManyToOne(targetEntity: User::class)] // On pointe vers l'entité User
    #[ORM\JoinColumn(nullable: true)] // On la rend nullable si un devis n'a pas de PAO
    private ?User $pao = null; // Le nom de la propriété peut rester 'pao'

    #[ORM\Column(length: 50)]
    private ?string $statut = self::STATUT_ENVOYE; // Statut par défaut

    #[ORM\Column]
    private ?bool $batOk = false;

    #[ORM\OneToOne(mappedBy: 'devisOrigine', targetEntity: Commande::class)]
    private ?Commande $commandeGeneree = null;

    #[ORM\Column(type: 'float')]
    private float $acompte = 0;

    #[ORM\Column(type: 'float')]
    private float $remise = 0; // remise en valeur monétaire

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

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
        
    public function getModeDePaiement(): ?string { return $this->modeDePaiement; }
    public function setModeDePaiement(?string $modeDePaiement): static { $this->modeDePaiement = $modeDePaiement; return $this; }

    public function getPao(): ?User // Le type de retour change
    { 
        return $this->pao; 
    }
    public function setPao(?User $pao): static // Le type de l'argument change
    { 
        $this->pao = $pao; 
        return $this; 
    }
    
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function isBatOk(): ?bool { return $this->batOk; }
    public function setBatOk(bool $batOk): static { $this->batOk = $batOk; return $this; }

    public function getCommandeGeneree(): ?Commande
    {
        return $this->commandeGeneree;
    }

    public function setCommandeGeneree(?Commande $commande): self
    {
        $this->commandeGeneree = $commande;
        return $this;
    }

    public function getAcompte(): float { return $this->acompte; }
    public function setAcompte(float $acompte): static { $this->acompte = $acompte; return $this; }
    
    // === AJOUTE UNE MÉTHODE UTILE POUR LE RESTE À PAYER ===
    public function getResteAPayer(): float
    {
        return $this->getTotal() - $this->getAcompte();
    }

    public function getDetailsPaiement(): ?string { return $this->detailsPaiement; }
    public function setDetailsPaiement(?string $detailsPaiement): static { $this->detailsPaiement = $detailsPaiement; return $this; }
    
    public function getMethodePaiement(): ?string { return $this->methodePaiement; }
    public function setMethodePaiement(?string $methodePaiement): static { $this->methodePaiement = $methodePaiement; return $this; }

    public function getRemise(): float { return $this->remise; }
    public function setRemise(float $remise): static { $this->remise = $remise; return $this; }

    public function getDateExpiration(): ?\DateTimeInterface
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?\DateTimeInterface $dateExpiration): self
    {
        $this->dateExpiration = $dateExpiration;
        return $this;
    }

    public function __toString(): string
    {
        // Exemple : afficher "Devis #ID - Client: NomClient - Total: 123.45"
        $clientName = $this->client ? $this->client->getNom() : 'Client inconnu';
        $total = number_format($this->total, 2, '.', '');
        return sprintf('Devis #%d - Client: %s - Total: %s', $this->id ?? 0, $clientName, $total);
    }

}
