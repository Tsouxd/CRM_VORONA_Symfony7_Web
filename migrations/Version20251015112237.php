<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015112237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE arret_de_caisse CHANGE fond_de_caisse_initial fond_de_caisse_initial DOUBLE PRECISION DEFAULT NULL, CHANGE details_paiements details_paiements JSON NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE lieu_livraison lieu_livraison VARCHAR(255) DEFAULT NULL, CHANGE heure_livraison heure_livraison TIME DEFAULT NULL, CHANGE nif nif VARCHAR(255) DEFAULT NULL, CHANGE stat stat VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande CHANGE piece_jointe piece_jointe VARCHAR(255) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE categorie categorie VARCHAR(50) DEFAULT NULL, CHANGE priorite priorite VARCHAR(20) DEFAULT NULL, CHANGE statut_pao statut_pao VARCHAR(50) DEFAULT NULL, CHANGE pao_bat_validation pao_bat_validation VARCHAR(50) DEFAULT NULL, CHANGE statut_production statut_production VARCHAR(50) DEFAULT NULL, CHANGE nom_livreur nom_livreur VARCHAR(100) DEFAULT NULL, CHANGE statut_livraison statut_livraison VARCHAR(50) DEFAULT NULL, CHANGE date_de_livraison date_de_livraison DATETIME DEFAULT NULL, CHANGE statut_devis statut_devis VARCHAR(50) DEFAULT NULL, CHANGE lieu_de_livraison lieu_de_livraison VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE depense CHANGE description description VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE devis CHANGE mode_de_paiement mode_de_paiement VARCHAR(100) DEFAULT NULL, CHANGE details_paiement details_paiement VARCHAR(255) DEFAULT NULL, CHANGE methode_paiement methode_paiement VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE facture CHANGE livreur livreur VARCHAR(255) DEFAULT NULL, CHANGE mode_de_paiement mode_de_paiement VARCHAR(100) DEFAULT NULL, CHANGE details_paiement details_paiement VARCHAR(255) DEFAULT NULL, CHANGE methode_paiement methode_paiement VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement CHANGE reference_paiement reference_paiement VARCHAR(100) DEFAULT NULL, CHANGE details_paiement details_paiement VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE revenu CHANGE description description VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE is_verified is_verified TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_request CHANGE role_demander role_demander VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE arret_de_caisse CHANGE fond_de_caisse_initial fond_de_caisse_initial DOUBLE PRECISION DEFAULT 'NULL', CHANGE details_paiements details_paiements LONGTEXT NOT NULL COLLATE `utf8mb4_bin`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client CHANGE telephone telephone VARCHAR(20) DEFAULT 'NULL', CHANGE lieu_livraison lieu_livraison VARCHAR(255) DEFAULT 'NULL', CHANGE heure_livraison heure_livraison TIME DEFAULT 'NULL', CHANGE nif nif VARCHAR(255) DEFAULT 'NULL', CHANGE stat stat VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE commande CHANGE piece_jointe piece_jointe VARCHAR(255) DEFAULT 'NULL', CHANGE updated_at updated_at DATETIME DEFAULT 'NULL', CHANGE categorie categorie VARCHAR(50) DEFAULT 'NULL', CHANGE priorite priorite VARCHAR(20) DEFAULT 'NULL', CHANGE statut_pao statut_pao VARCHAR(50) DEFAULT 'NULL', CHANGE pao_bat_validation pao_bat_validation VARCHAR(50) DEFAULT 'NULL', CHANGE statut_production statut_production VARCHAR(50) DEFAULT 'NULL', CHANGE nom_livreur nom_livreur VARCHAR(100) DEFAULT 'NULL', CHANGE statut_livraison statut_livraison VARCHAR(50) DEFAULT 'NULL', CHANGE date_de_livraison date_de_livraison DATETIME DEFAULT 'NULL', CHANGE statut_devis statut_devis VARCHAR(50) DEFAULT 'NULL', CHANGE lieu_de_livraison lieu_de_livraison VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE depense CHANGE description description VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE devis CHANGE mode_de_paiement mode_de_paiement VARCHAR(100) DEFAULT 'NULL', CHANGE details_paiement details_paiement VARCHAR(255) DEFAULT 'NULL', CHANGE methode_paiement methode_paiement VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE facture CHANGE livreur livreur VARCHAR(255) DEFAULT 'NULL', CHANGE mode_de_paiement mode_de_paiement VARCHAR(100) DEFAULT 'NULL', CHANGE details_paiement details_paiement VARCHAR(255) DEFAULT 'NULL', CHANGE methode_paiement methode_paiement VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT 'NULL' COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement CHANGE reference_paiement reference_paiement VARCHAR(100) DEFAULT 'NULL', CHANGE details_paiement details_paiement VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE revenu CHANGE description description VARCHAR(255) DEFAULT 'NULL'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE is_verified is_verified TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_request CHANGE role_demander role_demander VARCHAR(255) DEFAULT 'NULL'
        SQL);
    }
}
