<?php
// src/Command/CommandesUpdateRecouvrementCommand.php

namespace App\Command;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// On définit le nom de la commande et sa description ici
#[AsCommand(
    name: 'app:commandes:update-recouvrement',
    description: 'Passe automatiquement les commandes impayées et échues en statut RECOUVREMENT.',
)]
// LE NOM DE LA CLASSE CORRESPOND MAINTENANT AU NOM DU FICHIER
class CommandesUpdateRecouvrementCommand extends Command
{
    // On déclare les services dont on aura besoin
    private CommandeRepository $commandeRepository;
    private EntityManagerInterface $entityManager;

    // Le constructeur reçoit les services grâce à l'injection de dépendances de Symfony
    public function __construct(CommandeRepository $commandeRepository, EntityManagerInterface $entityManager)
    {
        $this->commandeRepository = $commandeRepository;
        $this->entityManager = $entityManager;
        
        parent::__construct();
    }

    // La méthode configure() n'est plus nécessaire car nous n'avons pas d'arguments ou d'options
    protected function configure(): void
    {
        // On peut la laisser vide ou la supprimer
    }

    // C'est ici que toute la logique s'exécute
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // SymfonyStyle est un outil pratique pour afficher des messages dans la console
        $io = new SymfonyStyle($input, $output);
        $io->title('Début de la mise à jour des statuts de recouvrement...');

        // 1. On récupère les commandes à traiter depuis le repository
        $commandesARecouvrer = $this->commandeRepository->findCommandesEchuesNonPayees();
        $nombreCommandes = count($commandesARecouvrer);

        // Si aucune commande n'est en retard, on arrête
        if ($nombreCommandes === 0) {
            $io->success('Aucune commande en retard trouvée. Tout est à jour !');
            return Command::SUCCESS; // Signifie que la commande s'est bien terminée
        }

        $io->comment("Trouvé $nombreCommandes commande(s) à passer en recouvrement...");

        // On affiche une barre de progression pour le suivi
        $io->progressStart($nombreCommandes);

        // 2. On boucle sur chaque commande trouvée et on change son statut
        /** @var Commande $commande */
        foreach ($commandesARecouvrer as $commande) {
            $commande->setStatutComptable(Commande::STATUT_COMPTABLE_RECOUVREMENT);
            $io->progressAdvance();
        }

        // 3. On enregistre TOUTES les modifications en base de données en une seule fois
        $this->entityManager->flush();
        
        $io->progressFinish();

        $io->success("$nombreCommandes commande(s) ont été passée(s) en statut RECOUVREMENT.");

        return Command::SUCCESS; // Signifie que la commande s'est bien terminée
    }
}