<?php

// src/Entity/Commande.php
namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[Vich\Uploadable]
class Commande
{
    public const PRIORITES = ['urgent', 'normal', 'faible'];

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

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $demandeModificationStatut = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $demandeModificationMotif = null;

    // --- NOUVELLES PROPRIÉTÉS ---
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $referencePaiement = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $priorite = null;

    #[ORM\ManyToOne(inversedBy: 'commandes', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pao $pao = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $statutPao = null; // => utilise camelCase

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

    public function getDemandeModificationStatut(): ?string { return $this->demandeModificationStatut; }
    public function setDemandeModificationStatut(?string $demandeModificationStatut): self { $this->demandeModificationStatut = $demandeModificationStatut; return $this; }

    public function getDemandeModificationMotif(): ?string { return $this->demandeModificationMotif; }
    public function setDemandeModificationMotif(?string $demandeModificationMotif): self { $this->demandeModificationMotif = $demandeModificationMotif; return $this; }

    // --- NOUVEAUX GETTERS/SETTERS ---
    public function getReferencePaiement(): ?string { return $this->referencePaiement; }
    public function setReferencePaiement(?string $referencePaiement): self { $this->referencePaiement = $referencePaiement; return $this; }

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

    public function getPao(): ?Pao
    {
        return $this->pao;
    }

    public function setPao(?Pao $pao): self
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

    public function __toString(): string
    {
        return 'Commande n°' . $this->getId() . ' - ' . ($this->getClient() ? $this->getClient()->getNom() : 'N/A');
    }
}