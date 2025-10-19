<?php
namespace App\Controller\Production;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\BonDeLivraison;
use App\Entity\BonDeLivraisonLigne;
use App\Repository\BonDeLivraisonRepository; 
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;

class ProductionCommandeCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;

    // On injecte RequestStack pour pouvoir lire les paramètres de l'URL
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInPlural('Gestion de Production')
                    ->setEntityLabelInSingular('Commande')
                    ->setDefaultSort(['dateCommande' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // 1. On crée notre nouvelle action personnalisée
        $imprimerFiche = Action::new('imprimerFiche', 'Imprimer Fiche', 'fa fa-print')
            ->linkToCrudAction('genererFicheTravailPdf') // Le nom de la méthode qu'on va créer
            ->setCssClass('btn btn-primary')
            ->setHtmlAttributes(['target' => '_blank']); // Ouvre dans un nouvel onglet

        $genererOuVoirBl = Action::new('genererBl', 'Générer / Voir BL', 'fa fa-truck')
                    ->linkToCrudAction('genererOuVoirBlAction') // On change le nom de la méthode cible
                    ->setCssClass('btn btn-primary')
                    // La condition d'affichage est maintenant plus simple :
                    // On affiche le bouton dès que la production est marquée comme terminée.
                    ->displayIf(fn (Commande $c) => $c->isProductionTermineeOk() === true)
                    // On change le label du bouton dynamiquement !
                    ->setLabel(fn (Commande $c) => $c->isBlGenere() ? 'Voir le BL' : 'Générer le BL')
                    ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)

            // 2. On ajoute notre bouton à la liste et à la page de détail
            ->add(Crud::PAGE_INDEX, $imprimerFiche)
            ->add(Crud::PAGE_DETAIL, $imprimerFiche)

            ->add(Crud::PAGE_INDEX, $genererOuVoirBl)
            ->add(Crud::PAGE_DETAIL, $genererOuVoirBl);
    }

    // === LA MÉTHODE CORRIGÉE ===
    public function genererOuVoirBlAction(
        AdminContext $context,
        EntityManagerInterface $em,
        BonDeLivraisonRepository $blRepository,
        CommandeRepository $commandeRepository // <-- On injecte le repository de Commande
    ): Response
    {
        // On récupère l'ID de la commande depuis le contexte
        $commandeId = $context->getEntity()->getPrimaryKeyValue();
        
        // IMPORTANT : On recharge l'entité depuis la base de données via son repository.
        // Cela garantit qu'on travaille sur une entité "fraîche" et gérée par Doctrine.
        $commande = $commandeRepository->find($commandeId);

        if (!$commande) {
            $this->addFlash('danger', 'Commande introuvable.');
            return $this->redirect($context->getReferrer());
        }
        
        // On cherche s'il existe déjà un BL pour cette commande
        $bonDeLivraison = $blRepository->findOneBy(['commande' => $commande]);

        if ($bonDeLivraison === null) {
            // --- CAS 1 : Le BL n'existe pas, on le CRÉE ---
            $bonDeLivraison = new BonDeLivraison($commande);
            foreach ($commande->getCommandeProduits() as $commandeLigne) {
                $blLigne = new BonDeLivraisonLigne();
                $blLigne->setDescriptionProduit($commandeLigne->getProduit()->getNom());
                $blLigne->setQuantite($commandeLigne->getQuantite());
                $bonDeLivraison->addLigne($blLigne);
            }
            
            // On met à jour le statut sur notre entité Commande fraîchement chargée
            $commande->setBlGenere(true);

            // Pas besoin de faire persist($commande), Doctrine le suit déjà.
            // Il suffit de persister le NOUVEL objet.
            $em->persist($bonDeLivraison);
            $em->flush(); // Le flush va sauvegarder le NOUVEAU BL ET la modification sur la Commande.
            
            //$this->addFlash('success', 'Le Bon de Livraison a été généré avec succès.');
        }

        // --- CAS 2 : Le BL existe déjà (ou vient d'être créé), on l'AFFICHE ---
        
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
                
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/utils/logo/forever.jpeg';
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));

        $html = $this->renderView('production/bon_de_livraison_pdf.html.twig', [
            'commande' => $commande,
            'logo' => $logoBase64,
            'bonDeLivraison' => $bonDeLivraison,
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="BL-'.$commande->getId().'.pdf"',
            ]
        );
    }

    // 3. On crée la méthode qui va générer le PDF
    public function genererFicheTravailPdf(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        /** @var Commande $commande */
        $commande = $context->getEntity()->getInstance();

        if (!$commande) {
            throw $this->createNotFoundException("Commande non trouvée");
        }

        // Configuration de Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        // On rend notre template Twig en HTML
        $html = $this->renderView('production/fiche_travail_pdf.html.twig', [
            'commande' => $commande,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // On envoie le PDF au navigateur
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                // 'inline' affiche le PDF dans le navigateur, 'attachment' le télécharge
                'Content-Disposition' => 'inline; filename="fiche-travail-'.$commande->getId().'.pdf"',
            ]
        );
    }

    // === C'EST LA MÉTHODE QUI APPLIQUE LE FILTRE ===
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        
        // --- Filtre 1 : Restreindre à l'utilisateur Production connecté (si ce n'est pas un admin) ---
        if ($this->isGranted('ROLE_PRODUCTION') && !$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $qb->andWhere('entity.production = :currentUser')
               ->setParameter('currentUser', $user);
        }

        // --- Filtre 2 : "Travaux à Faire" ---
        $request = $this->requestStack->getCurrentRequest();
        if ($request->query->get('filtre') === 'a_faire') {
            $qb->andWhere('entity.statutProduction = :status')
               ->setParameter('status', Commande::STATUT_PRODUCTION_EN_COURS);
        }
        
        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        // === Panneau 1 : Informations Générales (Lecture seule) ===
        yield FormField::addPanel('Informations sur la Commande')->collapsible();
        yield CollectionField::new('bonsDeLivraison', 'Bons de Livraison')
            ->setTemplatePath('admin/fields/bons_de_livraison.html.twig')
            ->onlyOnDetail();
        yield DateTimeField::new('dateCommande', 'Date de Commande')->setFormTypeOption('disabled', true);
        yield AssociationField::new('client', 'Client')->setFormTypeOption('disabled', true);
        yield CollectionField::new('commandeProduits', 'Produits à produire')->setFormTypeOption('disabled', true);

        // === Panneau 2 : Détails PAO (Lecture seule) - C'EST L'AJOUT ===
        yield AssociationField::new('production', 'Responsable')
            ->hideOnIndex()
            ->setFormTypeOption('disabled', true);
        yield FormField::addPanel('Détails Techniques (Validés par PAO)')->collapsible();
        yield BooleanField::new('paoFichierOk', 'Fichier OK ?')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoBatOk', 'BAT Prêt ?')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif1Ok', 'M1 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif2Ok', 'M2 Faite')->setFormTypeOption('disabled', true);
        yield BooleanField::new('paoModif3Ok', 'M3 Faite')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM1', 'Historique Motif 1')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM2', 'Historique Motif 2')->setFormTypeOption('disabled', true);
        yield TextareaField::new('paoMotifM3', 'Historique Motif 3')->setFormTypeOption('disabled', true);
        
        // === Panneau 3 : Suivi de Production (Votre logique existante) ===
        yield FormField::addPanel('Suivi de la Production')->collapsible();
        yield ChoiceField::new('statutProduction', 'Statut de production')
            ->setChoices([
                'En attente' => Commande::STATUT_PRODUCTION_ATTENTE,
                'En cours de production' => Commande::STATUT_PRODUCTION_EN_COURS,
                'Prêt pour livraison' => Commande::STATUT_PRODUCTION_POUR_LIVRAISON,
            ])
            ->setFormTypeOption('disabled', true) // AUTOMATIQUE
            ->renderAsBadges([
                Commande::STATUT_PRODUCTION_ATTENTE => 'secondary',
                Commande::STATUT_PRODUCTION_EN_COURS => 'primary',
                Commande::STATUT_PRODUCTION_POUR_LIVRAISON => 'success',
            ]);

        // --- Panneau Livraison avec logique conditionnelle ---
        yield FormField::addPanel('Gestion de la Livraison');
        
        $context = $this->getContext();
        /** @var Commande $commande */
        $commande = $context->getEntity()->getInstance();
        $isProductionFinished = ($commande && $commande->getStatutProduction() === Commande::STATUT_PRODUCTION_POUR_LIVRAISON);

        yield TextField::new('lieuDeLivraison', 'Lieu de Livraison')
            ->setFormTypeOption('disabled', !$isProductionFinished)
            ->setHelp($isProductionFinished ? '' : 'Ce champ sera disponible une fois la production terminée.');

        yield TextField::new('nomLivreur', 'Nom du Livreur')
            //->setFormTypeOption('disabled', !$isProductionFinished)
            ->setHelp($isProductionFinished ? '' : 'Ce champ sera disponible une fois la production terminée.');
            
        yield DateTimeField::new('dateDeLivraison', 'Date de Livraison Prévue');
            //->setFormTypeOption('disabled', !$isProductionFinished);
        yield ChoiceField::new('statutLivraison', 'Statut de Livraison')
            ->setChoices([
                'Prêt pour livraison' => Commande::STATUT_LIVRAISON_ATTENTE,
                'Livrée' => Commande::STATUT_LIVRAISON_LIVREE,
                'Retournée' => Commande::STATUT_LIVRAISON_RETOUR,
                'Annulée' => Commande::STATUT_LIVRAISON_ANNULEE,
            ])
            ->renderAsBadges([
                Commande::STATUT_LIVRAISON_ATTENTE => 'primary',
                Commande::STATUT_LIVRAISON_RETOUR => 'warning',
                Commande::STATUT_LIVRAISON_LIVREE => 'success',
                Commande::STATUT_LIVRAISON_ANNULEE => 'danger',
            ]);
            //->setFormTypeOption('disabled', !$isProductionFinished);
            
        yield BooleanField::new('productionTermineeOk', 'Marquer comme "Production Terminée"')
            ->setHelp('Cochez cette case lorsque la commande est entièrement produite et prête à être livrée. Le statut changera automatiquement.')
            ->setFormTypeOption('attr', ['class' => 'autosubmit-checkbox'])
            ->onlyOnForms();

        yield BooleanField::new('productionTermineeOk', 'Marquer comme "Production Terminée"')
            ->setHelp('Cochez cette case lorsque la commande est entièrement produite et prête à être livrée. Le statut changera automatiquement.')
            ->setFormTypeOption('attr', ['class' => 'autosubmit-checkbox'])
            ->setFormTypeOption('disabled', true) // AUTOMATIQUE
            ->onlyOnIndex();

        // Le script JS pour l'auto-soumission reste à la fin, c'est parfait
        yield FormField::addPanel('')->setHelp(<<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.autosubmit-checkbox input[type=checkbox]').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            this.closest('form').submit();
                        });
                    });
                });
            </script>
        HTML)->setCssClass('d-none');
    }
}