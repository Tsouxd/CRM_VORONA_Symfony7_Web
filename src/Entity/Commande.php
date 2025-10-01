<?php

// src/Entity/Commande.php
namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\User;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[Vich\Uploadable]
class Commande
{
    public const STATUT_PAO_ATTENTE = 'en attente';
    public const STATUT_PAO_EN_COURS = 'en cours';
    public const STATUT_PAO_FAIT = 'fait';
    public const STATUT_PAO_MODIFICATION = 'modification';

    public const BAT_EN_ATTENTE = 'En attente de validation';
    public const BAT_MODIFICATION = 'Modification demandée';
    public const BAT_PRODUCTION = 'Validé pour production';

    public const STATUT_PRODUCTION_ATTENTE = 'En attente';
    public const STATUT_PRODUCTION_EN_COURS = 'En cours de production';
    public const STATUT_PRODUCTION_POUR_LIVRAISON = 'Prêt pour livraison';

    public const PRIORITES = ['urgent', 'normal', 'faible'];

    public const STATUT_LIVRAISON_ATTENTE = 'Prêt pour livraison';
    public const STATUT_LIVRAISON_LIVREE = 'Livrée';
    public const STATUT_LIVRAISON_RETOUR = 'Retournée';
    public const STATUT_LIVRAISON_ANNULEE = 'Annulée';

    public const STATUT_DEVIS_VALIDEE = 'Validée';
    public const STATUT_DEVIS_NON_VALIDEE = 'Non validée';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(type: "boolean")]
    private bool $isFacture = false;

    #[ORM\ManyToOne(inversedBy: 'commandes', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $statut = 'en attente'; // valeur par défaut

    #[ORM\OneToMany(
        mappedBy: 'commande', 
        targetEntity: CommandeProduit::class, 
        cascade: ['persist', 'remove'], 
        orphanRemoval: true
    )]
    private Collection $commandeProduits;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Paiement::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $paiements;

    #[Vich\UploadableField(mapping: 'piece_jointe_commande', fileNameProperty: 'pieceJointe')]
    private ?File $pieceJointeFile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pieceJointe = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'float')]
    private float $fraisLivraison = 0.0;

    // --- NOUVELLES PROPRIÉTÉS ---
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $priorite = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $statutPao = self::STATUT_PAO_ATTENTE;

    // === NOUVEAUX CHAMPS POUR LE WORKFLOW PAO ===

    #[ORM\Column(type: 'boolean')]
    private bool $paoFichierOk = false;

    #[ORM\Column(type: 'boolean')]
    private bool $paoBatOk = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paoBatValidation = self::BAT_EN_ATTENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paoMotifModification = null;

    #[ORM\Column(type: 'boolean')]
    private bool $paoModif1Ok = false;

    #[ORM\Column(type: 'boolean')]
    private bool $paoModif2Ok = false;

    #[ORM\Column(type: 'boolean')]
    private bool $paoModif3Ok = false;

    // === NOUVEAUX CHAMPS POUR L'HISTORIQUE DES MOTIFS ===
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paoMotifM1 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paoMotifM2 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paoMotifM3 = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statutProduction = self::STATUT_PRODUCTION_ATTENTE; // Statut par défaut

    #[ORM\Column(type: 'boolean')]
    private bool $productionTermineeOk = false; // La case à cocher par la production

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nomLivreur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statutLivraison = self::STATUT_LIVRAISON_ATTENTE;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateDeLivraison = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $statutDevis = null;

    #[ORM\OneToOne(inversedBy: 'commandeGeneree', cascade: ['persist', 'remove'])]
    private ?Devis $devisOrigine = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'commandesPao')] // On pointe vers User
    #[ORM\JoinColumn(nullable: true)] // On la rend nullable pour ne pas casser les commandes sans PAO
    private ?User $pao = null; // On peut garder le nom 'pao' pour la clarté

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuDeLivraison = null;

    public function __construct()
    {
        $this->commandeProduits = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->dateCommande = new \DateTime(); 
    }

    public function __clone()
    {
        if ($this->id) {
            $this->commandeProduits = new ArrayCollection($this->commandeProduits->toArray());
        }
    }

    // --- GETTERS / SETTERS ---
    public function getId(): ?int { return $this->id; }
    public function getDateCommande(): ?\DateTimeInterface { return $this->dateCommande; }
    public function setDateCommande(\DateTimeInterface $dateCommande): static { $this->dateCommande = $dateCommande; return $this; }

    public function isFacture(): ?bool { return $this->isFacture; }
    public function setIsFacture(bool $isFacture): static { $this->isFacture = $isFacture; return $this; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    /**
     * @return Collection<int, CommandeProduit>
     */
    public function getCommandeProduits(): Collection { return $this->commandeProduits; }

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
            if ($commandeProduit->getCommande() === $this) {
                $commandeProduit->setCommande(null);
            }
        }
        return $this;
    }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getFraisLivraison(): float { return $this->fraisLivraison; }
    public function setFraisLivraison(float $fraisLivraison): self { $this->fraisLivraison = $fraisLivraison; return $this; }

    public function getTotal(): float
    {
        if ($this->getStatut() === 'annulée') return 0.0;
        $total = 0.0;
        foreach ($this->getCommandeProduits() as $commandeProduit) {
            if ($produit = $commandeProduit->getProduit()) {
                $total += $produit->getPrix() * $commandeProduit->getQuantite();
            }
        }
        return $total;
    }

    public function getTotalAvecFrais(): float { return $this->getTotal() + $this->getFraisLivraison(); }

    public function getMontantPaye(): float
    {
        $totalPaye = 0.0;
        foreach ($this->paiements as $paiement) {
            if ($paiement->getStatut() === 'effectué') $totalPaye += $paiement->getMontant();
        }
        return $totalPaye;
    }

    public function getResteAPayer(): float
    {
        $reste = $this->getTotalAvecFrais() - $this->getMontantPaye();
        return max(0, $reste);
    }

    public function updateStatutPaiement(): void
    {
        if (in_array($this->statut, ['annulée', 'livrée'])) return;

        $totalAPayer = $this->getTotalAvecFrais();
        if ($totalAPayer <= 0) { $this->setStatut('en attente'); return; }

        $resteAPayer = $this->getResteAPayer();

        if ($resteAPayer <= 0) $this->setStatut('payée');
        elseif ($this->getMontantPaye() > 0) $this->setStatut('partiellement payée');
        else if ($this->statut !== 'en cours') $this->setStatut('en attente');
    }

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection { return $this->paiements; }

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
            if ($paiement->getCommande() === $this) $paiement->setCommande(null);
        }
        return $this;
    }

    public function setPieceJointeFile(?File $file = null): void
    {
        $this->pieceJointeFile = $file;
        if ($file) $this->updatedAt = new \DateTimeImmutable();
    }
    public function getPieceJointeFile(): ?File { return $this->pieceJointeFile; }
    public function setPieceJointe(?string $pieceJointe): void { $this->pieceJointe = $pieceJointe; }
    public function getPieceJointe(): ?string { return $this->pieceJointe; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void { $this->updatedAt = $updatedAt; }

    // --- NOUVEAUX GETTERS/SETTERS ---
    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $categorie): self { $this->categorie = $categorie; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getPriorite(): ?string { return $this->priorite; }
    public function setPriorite(?string $priorite): self
    {
        if ($priorite && !in_array($priorite, self::PRIORITES)) {
            throw new \InvalidArgumentException("Priorité invalide");
        }
        $this->priorite = $priorite;
        return $this;
    }

    public function getPao(): ?User // <-- Le type de retour change en User
    {
        return $this->pao;
    }

    public function setPao(?User $pao): self // <-- Le type de l'argument change en User
    {
        $this->pao = $pao;
        return $this;
    }

    public function getStatutPao(): ?string
    {
        return $this->statutPao;
    }

    public function setStatutPao(?string $statutPao): self
    {
        $this->statutPao = $statutPao;
        return $this;
    }

    // === AJOUTEZ TOUS LES GETTERS ET SETTERS POUR LES NOUVEAUX CHAMPS CI-DESSOUS ===
    
    public function isPaoFichierOk(): bool { return $this->paoFichierOk; }
    public function setPaoFichierOk(bool $paoFichierOk): self { $this->paoFichierOk = $paoFichierOk; return $this; }

    public function isPaoBatOk(): bool { return $this->paoBatOk; }
    public function setPaoBatOk(bool $paoBatOk): self { $this->paoBatOk = $paoBatOk; return $this; }

    public function getPaoBatValidation(): ?string { return $this->paoBatValidation; }
    public function setPaoBatValidation(?string $paoBatValidation): self { $this->paoBatValidation = $paoBatValidation; return $this; }

    public function getPaoMotifModification(): ?string { return $this->paoMotifModification; }
    public function setPaoMotifModification(?string $paoMotifModification): self { $this->paoMotifModification = $paoMotifModification; return $this; }

    public function isPaoModif1Ok(): bool { return $this->paoModif1Ok; }
    public function setPaoModif1Ok(bool $paoModif1Ok): self { $this->paoModif1Ok = $paoModif1Ok; return $this; }

    public function isPaoModif2Ok(): bool { return $this->paoModif2Ok; }
    public function setPaoModif2Ok(bool $paoModif2Ok): self { $this->paoModif2Ok = $paoModif2Ok; return $this; }

    public function isPaoModif3Ok(): bool { return $this->paoModif3Ok; }
    public function setPaoModif3Ok(bool $paoModif3Ok): self { $this->paoModif3Ok = $paoModif3Ok; return $this; }

    // === AJOUTEZ LES GETTERS/SETTERS POUR CES 3 NOUVEAUX CHAMPS ===
    public function getPaoMotifM1(): ?string { return $this->paoMotifM1; }
    public function setPaoMotifM1(?string $paoMotifM1): self { $this->paoMotifM1 = $paoMotifM1; return $this; }

    public function getPaoMotifM2(): ?string { return $this->paoMotifM2; }
    public function setPaoMotifM2(?string $paoMotifM2): self { $this->paoMotifM2 = $paoMotifM2; return $this; }

    public function getPaoMotifM3(): ?string { return $this->paoMotifM3; }
    public function setPaoMotifM3(?string $paoMotifM3): self { $this->paoMotifM3 = $paoMotifM3; return $this; }

    public function getStatutProduction(): ?string { return $this->statutProduction; }
    public function setStatutProduction(?string $statutProduction): self { $this->statutProduction = $statutProduction; return $this; }

    public function isProductionTermineeOk(): bool { return $this->productionTermineeOk; }
    public function setProductionTermineeOk(bool $productionTermineeOk): self { $this->productionTermineeOk = $productionTermineeOk; return $this; }

    public function getNomLivreur(): ?string { return $this->nomLivreur; }
    public function setNomLivreur(?string $nomLivreur): self { $this->nomLivreur = $nomLivreur; return $this; }

    public function getStatutLivraison(): ?string { return $this->statutLivraison; }
    public function setStatutLivraison(?string $statutLivraison): self { $this->statutLivraison = $statutLivraison; return $this; }

    public function getDateDeLivraison(): ?\DateTimeInterface
    {
        return $this->dateDeLivraison;
    }

    public function setDateDeLivraison(?\DateTimeInterface $dateDeLivraison): static
    {
        $this->dateDeLivraison = $dateDeLivraison;

        return $this;
    }

    public function getStatutDevis(): ?string
    {
        return $this->statutDevis;
    }

    public function setStatutDevis(string $statutDevis): self
    {
        $this->statutDevis = $statutDevis;
        return $this;
    }

    // Getter
    public function getDevisOrigine(): ?Devis
    {
        return $this->devisOrigine;
    }

    // Setter
    public function setDevisOrigine(?Devis $devisOrigine): self
    {
        $this->devisOrigine = $devisOrigine;

        return $this;
    }

    public function getLieuDeLivraison(): ?string { return $this->lieuDeLivraison; }
    public function setLieuDeLivraison(?string $lieuDeLivraison): static { $this->lieuDeLivraison = $lieuDeLivraison; return $this; }

    public function __toString(): string
    {
        return 'Commande n°' . $this->getId() . ' - ' . ($this->getClient() ? $this->getClient()->getNom() : 'N/A');
    }
}