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
    /**
     * Affiche la page principale avec tous les "slides".
     * Prépare les données pour le tableau de bord, le formulaire de clôture et l'historique.
     */
    #[Route('/', name: 'app_comptable')]
    public function index(PaiementRepository $paiementRepo, ArretDeCaisseRepository $arretDeCaisseRepo): Response
    {
        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $totalsToClose = [];
        // ✅ ON UTILISE LA CLÉ 'referencePaiement'
        foreach ($totalsToCloseRaw as $row) {
            $totalsToClose[$row['referencePaiement']] = $row['totalTheorique'];
        }
        $pastClotures = $arretDeCaisseRepo->findBy([], ['dateCloture' => 'DESC'], 10);
        return $this->render('comptable/index.html.twig', [
            'hasPaymentsToClose' => !empty($totalsToClose),
            'totals' => $totalsToClose,
            'pastClotures' => $pastClotures,
        ]);
    }

    /**
     * Traite la soumission du formulaire de clôture.
     */
    #[Route('/arret-de-caisse/submit', name: 'app_comptable_arret_de_caisse_submit', methods: ['POST'])]
    public function clotureSubmit(Request $request, PaiementRepository $paiementRepo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $arretDeCaisse = new ArretDeCaisse($user);

        // Récupérer les totaux théoriques
        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $totalsToClose = [];
        foreach ($totalsToCloseRaw as $row) {
            $totalsToClose[$row['referencePaiement']] = $row['totalTheorique'];
        }
        
        $fondInitial = (float) $request->request->get('fond_de_caisse_initial', 0);
        $arretDeCaisse->setFondDeCaisseInitial($fondInitial);
        
        $details = [];
        foreach ($totalsToClose as $referencePaiement => $theorique) {
            // Le nom du champ dans le formulaire est basé sur 'referencePaiement'
            $compte = (float) $request->request->get('compte_' . strtolower(str_replace(' ', '_', $referencePaiement)), 0);
            
            $ecart = $compte - $theorique;
            if ($referencePaiement === 'Espèces') {
                $ecart = $compte - ($theorique + $fondInitial);
            }
            $details[$referencePaiement] = [ 
                'theorique' => $theorique,
                'compte' => $compte,
                'ecart' => $ecart
            ];
        }
        $arretDeCaisse->setDetailsPaiements($details);
        $arretDeCaisse->setNotes($request->request->get('notes'));

        // Associer les paiements à cette clôture
        $paiements = $paiementRepo->findBy(['arretDeCaisse' => null, 'statut' => 'effectué']);
        foreach ($paiements as $paiement) {
            $paiement->setArretDeCaisse($arretDeCaisse);
        }

        $em->persist($arretDeCaisse);
        $em->flush();

        $this->addFlash('success', 'La caisse a été clôturée avec succès.');
        return $this->redirectToRoute('app_comptable');
    }
}