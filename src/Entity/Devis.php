<?php
namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User; 

#[ORM\Entity(repositoryClass: DevisRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Devis
{
    public const STATUT_ENVOYE = 'Envoyé';
    public const STATUT_BAT_PRODUCTION = 'BAT/Production';
    public const STATUT_RELANCE = 'Relance';
    public const STATUT_PERDU = 'Perdu';

    public const METHODE_100_COMMANDE = '100% commande';
    public const METHODE_50_50 = '50% commande, 50% livraison';
    public const METHODE_100_LIVRAISON = '100% livraison';
    public const METHODE_30_JOURS = '30 jours fin de mois';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\OneToMany(mappedBy: "devis", targetEntity: DevisLigne::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $lignes;

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

    #[ORM\Column(type: 'float', nullable: true)]
    private float $acompte = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    private float $remise = 0; // remise en valeur monétaire

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $commercial = null;

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

    public function getCommercial(): ?User
    {
        return $this->commercial;
    }

    public function setCommercial(?User $commercial): static
    {
        $this->commercial = $commercial;
        return $this;
    }

    public function getTotalBrut(): float
    {
        $total = 0;
        foreach ($this->lignes as $ligne) {
            $total += $ligne->getPrixTotal();
        }
        return $total;
    }

    public function getResteAPayer(): float
    {
        return $this->getTotalBrut() - $this->getAcompte();
    }

    public function calculerAcompteAutomatique(): void
    {
        $total = $this->getTotalBrut();
        $methode = $this->getMethodePaiement();
        $nouvelAcompte = 0.0;

        switch ($methode) {
            case self::METHODE_100_COMMANDE: // Utilisation de la constante
                $nouvelAcompte = $total;
                break;
            case self::METHODE_50_50: // Utilisation de la constante
                $nouvelAcompte = $total * 0.5;
                break;
            // Pour tous les autres cas, l'acompte est de 0
            case self::METHODE_100_LIVRAISON:
            case self::METHODE_30_JOURS:
            default:
                $nouvelAcompte = 0.0;
                break;
        }

        $this->setAcompte($nouvelAcompte);
    }
    
    // Vous pouvez garder ce callback, il ne fait pas de mal et fonctionnera en EDIT
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onPrePersistOrUpdate(): void
    {
        $this->calculerAcompteAutomatique();
    }

    public function __toString(): string
    {
        //$clientName = $this->client ? $this->client->getNom() : 'Client inconnu';
        //$total = $this->getTotalBrut();
        return sprintf('Devis n°%d', $this->id ?? 0);
    }
}
