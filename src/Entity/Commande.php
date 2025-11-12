<?php

// src/Entity/Commande.php
namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\User;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use App\Entity\Paiement;
/*use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;*/
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\HasLifecycleCallbacks]
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

    public const STATUT_COMPTABLE_ATTENTE = 'EN_ATTENTE';
    public const STATUT_COMPTABLE_PARTIEL = 'PARTIEL';
    public const STATUT_COMPTABLE_PAYE = 'PAYE';
    public const STATUT_COMPTABLE_RECOUVREMENT = 'RECOUVREMENT';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(type: "boolean")]
    private bool $isFacture = false;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
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

    #[ORM\ManyToOne(inversedBy: 'commandeGeneree', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Devis $devisOrigine = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'commandesPao')] // On pointe vers User
    #[ORM\JoinColumn(nullable: true)] // On la rend nullable pour ne pas casser les commandes sans PAO
    private ?User $pao = null; // On peut garder le nom 'pao' pour la clarté

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuDeLivraison = null;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: BonDeLivraison::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $bonsDeLivraison;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)] // Une commande a toujours un créateur
    private ?User $commercial = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)] // On le met en nullable au cas où
    private ?User $production = null;

    #[ORM\Column]
    private bool $blGenere = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $paoStatusUpdatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $productionStatusUpdatedAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $bonDeCommande = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $methodePaiement = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateDeLivraisonPartielle = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $finition = null;

    #[ORM\OneToOne(mappedBy: 'commande', targetEntity: Facture::class)]
    private ?Facture $facture = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => self::STATUT_COMPTABLE_ATTENTE])]
    private ?string $statutComptable = self::STATUT_COMPTABLE_ATTENTE;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $verifieComptable = false;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[Vich\UploadableField(mapping: 'commandes_fichiers', fileNameProperty: 'modificationFileName')]
    private ?File $modificationFile = null;

    #[Vich\UploadableField(mapping: 'commandes_fichiers2', fileNameProperty: 'modificationFileName2')]
    private ?File $modificationFile2 = null;

    #[Vich\UploadableField(mapping: 'commandes_fichiers3', fileNameProperty: 'modificationFileName3')]
    private ?File $modificationFile3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modificationFileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modificationFileName2 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paoMotifModification2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modificationFileName3 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paoMotifModification3 = null;

    public function __construct()
    {
        $this->commandeProduits = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->dateCommande = new \DateTime(); 
        $this->bonsDeLivraison = new ArrayCollection();
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

    /** @return Collection<int, BonDeLivraison> */
    public function getBonsDeLivraison(): Collection { return $this->bonsDeLivraison; }

    public function isBlGenere(): bool { return $this->blGenere; }
    public function setBlGenere(bool $blGenere): static { $this->blGenere = $blGenere; return $this; }

    public function getCommercial(): ?User { return $this->commercial; }
    public function setCommercial(?User $commercial): static { $this->commercial = $commercial; return $this; }

    public function getProduction(): ?User { return $this->production; }
    public function setProduction(?User $production): static { $this->production = $production; return $this; }

    public function getPaoStatusUpdatedAt(): ?\DateTimeInterface
    {
        return $this->paoStatusUpdatedAt;
    }

    public function setPaoStatusUpdatedAt(?\DateTimeInterface $paoStatusUpdatedAt): self
    {
        $this->paoStatusUpdatedAt = $paoStatusUpdatedAt;
        return $this;
    }

        public function getProductionStatusUpdatedAt(): ?\DateTimeInterface
    {
        return $this->productionStatusUpdatedAt;
    }

    public function setProductionStatusUpdatedAt(?\DateTimeInterface $productionStatusUpdatedAt): self
    {
        $this->productionStatusUpdatedAt = $productionStatusUpdatedAt;
        return $this;
    }
    
    /**
     * S'exécute à la création d'une nouvelle commande.
     */
    #[ORM\PrePersist]
    public function setStatusTimestampsOnCreate(): void
    {
        // Gère le statut PAO
        if (in_array($this->statutPao, [self::STATUT_PAO_FAIT, self::STATUT_PAO_MODIFICATION])) {
            $this->paoStatusUpdatedAt = new \DateTime();
        }
        
        // Gère le statut Production
        // Mettez ici les statuts qui signifient "terminé"
        if (in_array($this->statutProduction, [self::STATUT_PRODUCTION_POUR_LIVRAISON, 'Livrée'])) { // Remplacez 'Livrée' par la bonne constante si elle existe
            $this->productionStatusUpdatedAt = new \DateTime();
        }
    }

    /**
     * S'exécute AVANT chaque mise à jour d'une commande existante.
     */
    #[ORM\PreUpdate]
    public function updateStatusTimestampsOnUpdate(PreUpdateEventArgs $eventArgs): void
    {
        // On vérifie si le champ 'statutPao' a été modifié
        if ($eventArgs->hasChangedField('statutPao')) {
            $newStatus = $eventArgs->getNewValue('statutPao');
            if (in_array($newStatus, [self::STATUT_PAO_FAIT, self::STATUT_PAO_MODIFICATION])) {
                $this->paoStatusUpdatedAt = new \DateTime();
            }
        }
        
        // On vérifie si le champ 'statutProduction' a été modifié
        if ($eventArgs->hasChangedField('statutProduction')) {
            $newStatus = $eventArgs->getNewValue('statutProduction');
            // Mettez ici les statuts qui signifient "terminé" pour la production
            if (in_array($newStatus, [self::STATUT_PRODUCTION_POUR_LIVRAISON, 'Livrée'])) { // Remplacez 'Livrée' par la bonne constante
                $this->productionStatusUpdatedAt = new \DateTime();
            }
        }
    }

    public function addBonsDeLivraison(BonDeLivraison $bonsDeLivraison): static
    {
        if (!$this->bonsDeLivraison->contains($bonsDeLivraison)) {
            $this->bonsDeLivraison->add($bonsDeLivraison);
            $bonsDeLivraison->setCommande($this);
        }

        return $this;
    }

    public function removeBonsDeLivraison(BonDeLivraison $bonsDeLivraison): static
    {
        if ($this->bonsDeLivraison->removeElement($bonsDeLivraison)) {
            // set the owning side to null (unless already changed)
            if ($bonsDeLivraison->getCommande() === $this) {
                $bonsDeLivraison->setCommande(null);
            }
        }

        return $this;
    }

    public function getBonDeCommande(): ?string
    {
        return $this->bonDeCommande;
    }

    public function setBonDeCommande(?string $bonDeCommande): static
    {
        $this->bonDeCommande = $bonDeCommande;

        return $this;
    }

    public function getMethodePaiement(): ?string { return $this->methodePaiement; }
    public function setMethodePaiement(?string $methodePaiement): static { $this->methodePaiement = $methodePaiement; return $this; }

    public function recalculerPaiementsPourUpdate(?string $referencePaiement = null, ?string $detailsPaiement = null): void
    {
        $totalCommande = $this->getTotalAvecFrais();
        if ($totalCommande <= 0) {
            return;
        }

        // Par défaut, si rien n'est fourni
        if (empty($referencePaiement)) {
            $referencePaiement = 'Espèce';
        }

        $methode = $this->getMethodePaiement();

        // On récupère les paiements existants
        $paiementsExistants = $this->getPaiements()->toArray();

        // On vide les paiements automatiques
        foreach ($this->getPaiements() as $p) {
            $p->setCommande(null);
        }
        $this->getPaiements()->clear();

        switch ($methode) {
            case '100% commande':
                $paiement = new Paiement();
                $paiement->setMontant($totalCommande)
                        ->setDatePaiement(new \DateTime())
                        ->setStatut(Paiement::STATUT_EFFECTUE);

                if (!empty($paiementsExistants)) {
                    $paiement->setReferencePaiement($paiementsExistants[0]->getReferencePaiement() ?? $referencePaiement);
                    $paiement->setDetailsPaiement($paiementsExistants[0]->getDetailsPaiement() ?? $detailsPaiement);
                } else {
                    $paiement->setReferencePaiement($referencePaiement);
                    $paiement->setDetailsPaiement(is_array($detailsPaiement) ? json_encode($detailsPaiement, JSON_UNESCAPED_UNICODE) : $detailsPaiement);
                }

                $this->addPaiement($paiement);
                break;

            case '50% commande, 50% livraison':
                $acompte = round($totalCommande * 0.5);
                $solde = $totalCommande - $acompte;

                $paiementAcompte = new Paiement();
                $paiementAcompte->setMontant($acompte)
                                ->setDatePaiement(new \DateTime())
                                ->setStatut(Paiement::STATUT_EFFECTUE);

                $paiementSolde = new Paiement();
                $paiementSolde->setMontant($solde)
                            ->setDatePaiement(new \DateTime())
                            ->setStatut(Paiement::STATUT_A_VENIR);

                if (!empty($paiementsExistants)) {
                    $paiementAcompte->setReferencePaiement($paiementsExistants[0]->getReferencePaiement() ?? $referencePaiement);
                    $paiementAcompte->setDetailsPaiement($paiementsExistants[0]->getDetailsPaiement() ?? $detailsPaiement);

                    $paiementSolde->setReferencePaiement($paiementsExistants[0]->getReferencePaiement() ?? $referencePaiement);
                    $paiementSolde->setDetailsPaiement($paiementsExistants[0]->getDetailsPaiement() ?? $detailsPaiement);
                } else {
                    $paiementAcompte->setReferencePaiement($referencePaiement);
                    $paiementAcompte->setDetailsPaiement(is_array($detailsPaiement) ? json_encode($detailsPaiement, JSON_UNESCAPED_UNICODE) : $detailsPaiement);

                    $paiementSolde->setReferencePaiement($referencePaiement);
                    $paiementSolde->setDetailsPaiement(is_array($detailsPaiement) ? json_encode($detailsPaiement, JSON_UNESCAPED_UNICODE) : $detailsPaiement);
                }

                $this->addPaiement($paiementAcompte);
                $this->addPaiement($paiementSolde);
                break;

            case '100% à la livraison':
            case '30 jours après réception de la facture':
                $paiement = new Paiement();
                $paiement->setMontant($totalCommande)
                        ->setDatePaiement(new \DateTime())
                        ->setStatut(Paiement::STATUT_A_VENIR);

                if (!empty($paiementsExistants)) {
                    $paiement->setReferencePaiement($paiementsExistants[0]->getReferencePaiement() ?? $referencePaiement);
                    $paiement->setDetailsPaiement($paiementsExistants[0]->getDetailsPaiement() ?? $detailsPaiement);
                } else {
                    $paiement->setReferencePaiement($referencePaiement);
                    $paiement->setDetailsPaiement(is_array($detailsPaiement) ? json_encode($detailsPaiement, JSON_UNESCAPED_UNICODE) : $detailsPaiement);
                }

                $this->addPaiement($paiement);
                break;

            default:
                // Méthode non reconnue : aucun paiement créé
                break;
        }

        $this->updateStatutPaiement();
    }

    public function genererPaiementsAutomatiques(?string $referencePaiement = null, ?string $detailsPaiement = null): void
    {
        $totalCommande = $this->getTotalAvecFrais();
        if ($totalCommande <= 0) {
            return;
        }

        // Réinitialisation des paiements existants
        foreach ($this->getPaiements() as $paiementExistant) {
            $paiementExistant->setCommande(null);
        }
        $this->getPaiements()->clear();

        $methode = $this->getMethodePaiement();
        $paiementsExistants = $this->getPaiements(); // récupère la collection actuelle (vide après clear)

        // Fonction helper pour gérer reference/details en toute sécurité
        $setReferenceEtDetails = function(Paiement $paiement) use ($paiementsExistants, $referencePaiement, $detailsPaiement) {
            if (!$paiementsExistants->isEmpty()) {
                $paiement->setReferencePaiement($paiementsExistants[0]->getReferencePaiement() ?? $referencePaiement);

                $details = $paiementsExistants[0]->getDetailsPaiement() ?? $detailsPaiement;
                if (is_array($details)) {
                    $paiement->setDetailsPaiement(json_encode($details, JSON_UNESCAPED_UNICODE));
                } else {
                    $paiement->setDetailsPaiement($details);
                }
            } else {
                $paiement->setReferencePaiement($referencePaiement);

                if (is_array($detailsPaiement)) {
                    $paiement->setDetailsPaiement(json_encode($detailsPaiement, JSON_UNESCAPED_UNICODE));
                } else {
                    $paiement->setDetailsPaiement($detailsPaiement);
                }
            }
        };

        switch ($methode) {
            case '100% commande':
                $paiement = new Paiement();
                $paiement->setMontant($totalCommande)
                        ->setDatePaiement(new \DateTime())
                        ->setStatut(Paiement::STATUT_EFFECTUE);
                $setReferenceEtDetails($paiement);
                $this->addPaiement($paiement);
                break;

            case '50% commande, 50% livraison':
                $acompte = round($totalCommande * 0.5);
                $solde = $totalCommande - $acompte;

                // Paiement de l'acompte
                $paiementAcompte = new Paiement();
                $paiementAcompte->setMontant($acompte)
                                ->setDatePaiement(new \DateTime())
                                ->setStatut(Paiement::STATUT_EFFECTUE);
                $setReferenceEtDetails($paiementAcompte);

                // Paiement du solde
                $paiementSolde = new Paiement();
                $paiementSolde->setMontant($solde)
                            ->setDatePaiement(new \DateTime())
                            ->setStatut(Paiement::STATUT_A_VENIR);
                $setReferenceEtDetails($paiementSolde);

                $this->addPaiement($paiementAcompte);
                $this->addPaiement($paiementSolde);
                break;

            case '100% à la livraison':
                $paiement = new Paiement();
                $paiement->setMontant($totalCommande)
                        ->setDatePaiement(new \DateTime())
                        ->setStatut(Paiement::STATUT_A_VENIR);
                $setReferenceEtDetails($paiement);
                $this->addPaiement($paiement);
                break;

            case '30 jours après réception de la facture':
                $paiement = new Paiement();
                $paiement->setMontant($totalCommande)
                        ->setDatePaiement(new \DateTime())
                        ->setStatut(Paiement::STATUT_A_VENIR);
                $setReferenceEtDetails($paiement);
                $this->addPaiement($paiement);
                break;

            default:
                // Méthode non reconnue : aucun paiement créé
                break;
        }

        // Mise à jour du statut global de la commande
        $this->updateStatutPaiement();
    }

    /**
     * S'exécute juste avant qu'une NOUVELLE commande soit enregistrée.
     */
        #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->setStatusTimestampsOnCreate();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $this->updateStatusTimestampsOnUpdate($eventArgs);
    }

    // Getter
    public function getDateDeLivraisonPartielle(): ?\DateTimeInterface
    {
        return $this->dateDeLivraisonPartielle;
    }

    // Setter
    public function setDateDeLivraisonPartielle(?\DateTimeInterface $dateDeLivraisonPartielle): self
    {
        $this->dateDeLivraisonPartielle = $dateDeLivraisonPartielle;
        return $this;
    }

    // Getter et Setter pour dateDebut
    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    // Getter et Setter pour dateFin
    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getFinition(): ?string
    {
        return $this->finition;
    }

    public function setFinition(?string $finition): static
    {
        $this->finition = $finition;
        return $this;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    // Le setter n'est pas obligatoire car la relation est gérée par Facture, 
    // mais il peut être utile.
    public function setFacture(?Facture $facture): static
    {
        // s'assure que la relation est bien bidirectionnelle
        if ($facture !== null && $facture->getCommande() !== $this) {
            $facture->setCommande($this);
        }
        $this->facture = $facture;
        return $this;
    }

    public function getStatutComptable(): ?string
    {
        return $this->statutComptable;
    }

    public function setStatutComptable(string $statutComptable): static
    {
        $this->statutComptable = $statutComptable;
        return $this;
    }

    public function isVerifieComptable(): bool
    {
        return $this->verifieComptable;
    }

    public function setVerifieComptable(bool $verifieComptable): static
    {
        $this->verifieComptable = $verifieComptable;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeImmutable $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function setModificationFile(?File $modificationFile = null): void
    {
        $this->modificationFile = $modificationFile;

        if (null !== $modificationFile) {
            // Il est nécessaire de modifier au moins un champ pour que les listeners Doctrine
            // se déclenchent et que le fichier soit sauvegardé.
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getModificationFile(): ?File
    {
        return $this->modificationFile;
    }

    public function setModificationFileName(?string $modificationFileName): void
    {
        $this->modificationFileName = $modificationFileName;
    }

    public function getModificationFileName(): ?string
    {
        return $this->modificationFileName;
    }

    public function setModificationFile2(?File $modificationFile2 = null): void
    {
        $this->modificationFile2 = $modificationFile2;

        if (null !== $modificationFile2) {
            // Il est nécessaire de modifier au moins un champ pour que les listeners Doctrine
            // se déclenchent et que le fichier soit sauvegardé.
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getModificationFile2(): ?File
    {
        return $this->modificationFile2;
    }

    public function setModificationFileName2(?string $modificationFileName2): void
    {
        $this->modificationFileName2 = $modificationFileName2;
    }

    public function getModificationFileName2(): ?string
    {
        return $this->modificationFileName2;
    }

    public function getPaoMotifModification2(): ?string { return $this->paoMotifModification2; }
    public function setPaoMotifModification2(?string $paoMotifModification2): self { $this->paoMotifModification2 = $paoMotifModification2; return $this; }

    public function setModificationFile3(?File $modificationFile3 = null): void
    {
        $this->modificationFile3 = $modificationFile3;

        if (null !== $modificationFile3) {
            // Il est nécessaire de modifier au moins un champ pour que les listeners Doctrine
            // se déclenchent et que le fichier soit sauvegardé.
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getModificationFile3(): ?File
    {
        return $this->modificationFile3;
    }

    public function setModificationFileName3(?string $modificationFileName3): void
    {
        $this->modificationFileName3 = $modificationFileName3;
    }

    public function getModificationFileName3(): ?string
    {
        return $this->modificationFileName3;
    }

    public function getPaoMotifModification3(): ?string { return $this->paoMotifModification3; }
    public function setPaoMotifModification3(?string $paoMotifModification3): self { $this->paoMotifModification3 = $paoMotifModification3; return $this; }

    public function __toString(): string
    {
        return 'Commande n°' . $this->getId() . ' - ' . ($this->getClient() ? $this->getClient()->getNom() : 'Client inconnu');
    }
}