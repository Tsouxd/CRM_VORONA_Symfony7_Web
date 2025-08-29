<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Controller\Admin\CommandeCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;


class ClientCrudController extends AbstractCrudController
{
    private AdminUrlGenerator $adminUrlGenerator;
    private RequestStack $requestStack;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return Client::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Clients")
            ->setEntityLabelInSingular("Client");
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action; // Ici tu peux ajouter un label ou une icône personnalisée
            })
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE); // OK car SAVE_AND_CONTINUE n'existe pas encore sur PAGE_NEW
    }

    public function configureFields(string $pageName): iterable
    {
        // --- Panneau Informations Générales ---
        yield FormField::addPanel('Informations Générales');
        yield TextField::new('nom', 'Nom du client');
        yield EmailField::new('email', 'Adresse e-mail');
        yield TelephoneField::new('telephone', 'Numéro de téléphone');
        yield IdField::new('id')->onlyOnIndex();

        // --- Champ de choix du Type ---
        yield TextField::new('type')->onlyOnIndex(); // Pour l'affichage simple en liste

        yield ChoiceField::new('type')
            ->setChoices([
                'Particulier' => Client::TYPE_PARTICULIER,
                'Professionnel' => Client::TYPE_PROFESSIONNEL,
            ])
            ->renderExpanded()
            // On ajoute une classe sur le conteneur du champ pour que le JS le trouve facilement
            ->addCssClass('client-type-choice-container')
            ->onlyOnForms();

        // --- Panneau pour les Particuliers ---
        yield FormField::addPanel('Informations Particulier')
            // On ajoute une classe sur le panneau pour pouvoir le masquer en entier
            ->addCssClass('client-particulier-panel')
            ->onlyOnForms();
        yield TextareaField::new('adresseLivraison')->onlyOnForms();
        yield TextField::new('lieuLivraison')->onlyOnForms();
            
        // --- Panneau pour les Professionnels ---
        yield FormField::addPanel('Informations Professionnel')
            // On ajoute une classe sur le panneau pour pouvoir le masquer en entier
            ->addCssClass('client-professionnel-panel')
            ->onlyOnForms();
        yield TextField::new('nif')->onlyOnForms();
        yield TextField::new('stat')->onlyOnForms();
        yield TextareaField::new('adresse', 'Adresse (siège social)')->onlyOnForms();

        // ✅ INJECTION DU SCRIPT JS POUR GÉRER L'AFFICHAGE DYNAMIQUE
        yield FormField::addPanel('', '')
            ->onlyOnForms() // Ce panneau invisible n'apparaît que sur les formulaires
            ->setHelp(<<<HTML
<script>
    function initializeClientTypeToggle() {
        const typeChoiceContainer = document.querySelector('.client-type-choice-container');
        const particulierPanel = document.querySelector('.client-particulier-panel');
        const professionnelPanel = document.querySelector('.client-professionnel-panel');

        if (!typeChoiceContainer || !particulierPanel || !professionnelPanel) {
            return; // Si les éléments ne sont pas trouvés, on ne fait rien
        }

        function updatePanelVisibility() {
            // On trouve le bouton radio qui est actuellement coché
            const selectedRadio = typeChoiceContainer.querySelector('input[type="radio"]:checked');
            if (!selectedRadio) return;

            if (selectedRadio.value === 'particulier') {
                particulierPanel.style.display = 'block';
                professionnelPanel.style.display = 'none';
            } else if (selectedRadio.value === 'professionnel') {
                particulierPanel.style.display = 'none';
                professionnelPanel.style.display = 'block';
            }
        }

        // On écoute les changements sur les boutons radio
        typeChoiceContainer.addEventListener('change', updatePanelVisibility);

        // On exécute la fonction une première fois au chargement pour définir le bon état
        updatePanelVisibility();
    }

    // Lancement au chargement de la page
    document.addEventListener('DOMContentLoaded', initializeClientTypeToggle);
    // Lancement si la page est chargée via Turbo (navigation interne d'EasyAdmin)
    document.addEventListener('turbo:load', initializeClientTypeToggle);
</script>
HTML
            );
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);

        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('fromCommandeForm') && $request->isXmlHttpRequest()) {
            $response = new JsonResponse([
                'closeModal' => true,
                'callback' => sprintf(
                    "window.eaAutocompleteSelectNewValue('client', %d, '%s');",
                    $entityInstance->getId(),
                    addslashes($entityInstance->getNom())
                )
            ]);
            $response->send();
            exit;
        }
    }
}
