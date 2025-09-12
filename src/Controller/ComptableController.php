<?php

namespace App\Controller;

use App\Entity\ArretDeCaisse;
use App\Entity\User;
use App\Repository\ArretDeCaisseRepository;
use App\Repository\PaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comptable')]
#[IsGranted('ROLE_COMPTABLE')]
class ComptableController extends AbstractController
{
    #[Route('/', name: 'app_comptable')]
    public function index(PaiementRepository $paiementRepo, ArretDeCaisseRepository $arretDeCaisseRepo): Response
    {
        // Paiements à clôturer
        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $totalsToClose = [];
        foreach ($totalsToCloseRaw as $row) {
            $totalsToClose[$row['referencePaiement']] = $row['totalTheorique'];
        }

        // Historique des 10 dernières clôtures
        $pastClotures = $arretDeCaisseRepo->findBy([], ['dateCloture' => 'DESC'], 10);

        return $this->render('comptable/index.html.twig', [
            'hasPaymentsToClose' => !empty($totalsToClose),
            'totals' => $totalsToClose,
            'pastClotures' => $pastClotures,
        ]);
    }

    #[Route('/arret-de-caisse/submit', name: 'app_comptable_arret_de_caisse_submit', methods: ['POST'])]
    public function clotureSubmit(Request $request, PaiementRepository $paiementRepo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            // Cette vérification est bonne, on la garde
            $this->addFlash('danger', 'Utilisateur non authentifié.');
            return $this->redirectToRoute('app_login'); 
        }

        // On passe l'utilisateur directement au constructeur
        $arretDeCaisse = new ArretDeCaisse($user);
        
        // Comme le constructeur s'occupe déjà de l'utilisateur et de la date,
        // les lignes suivantes ne sont plus nécessaires. On peut les supprimer.
        // $arretDeCaisse->setUtilisateur($user); // Déjà fait par le constructeur
        // $arretDeCaisse->setDateCloture(new \DateTimeImmutable()); // Déjà fait par le constructeur

        // Le reste de ton code est parfait et ne change pas.
        $fondInitial = (float) $request->request->get('fond_de_caisse_initial', 0);
        $arretDeCaisse->setFondDeCaisseInitial($fondInitial);

        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $details = [];

        foreach ($totalsToCloseRaw as $row) {
            $reference = $row['referencePaiement'];
            $theorique = (float) $row['totalTheorique'];
            $compte = 0;
            $billetage = [];

            if ($reference === 'Espèces') {
                $valeurs = [100000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];
                foreach ($valeurs as $val) {
                    $qte = (int) $request->request->get('billet_' . $val, 0);
                    if ($qte > 0) { // On ne stocke que les billets utilisés
                        $billetage[$val] = $qte;
                    }
                    $compte += $val * $qte;
                }
                $ecart = $compte - ($theorique + $fondInitial);
                $details[$reference] = [
                    'theorique' => $theorique,
                    'compte' => $compte,
                    'ecart' => $ecart,
                    'billetage' => $billetage
                ];
            } else {
                $compte = (float) $request->request->get('compte_' . strtolower(str_replace(' ', '_', $reference)), 0);
                $ecart = $compte - $theorique;
                $details[$reference] = [
                    'theorique' => $theorique,
                    'compte' => $compte,
                    'ecart' => $ecart,
                ];
            }
        }

        $arretDeCaisse->setDetailsPaiements($details);
        $arretDeCaisse->setNotes($request->request->get('notes', ''));

        $paiements = $paiementRepo->findBy(['arretDeCaisse' => null, 'statut' => 'effectué']);
        foreach ($paiements as $paiement) {
            $arretDeCaisse->addPaiement($paiement); // Utiliser la méthode addPaiement est plus propre
        }

        $em->persist($arretDeCaisse);
        $em->flush();

        $this->addFlash('success', 'La caisse a été clôturée avec succès.');
        return $this->redirectToRoute('app_comptable');
    }
}