<?php

namespace App\Controller;

use App\Entity\ArretDeCaisse;
use App\Entity\Commande;
use App\Entity\User;
use App\Entity\Paiement;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\ArretDeCaisseRepository;
use App\Repository\CommandeRepository;
use App\Repository\PaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comptable')] // Route de base unifiée
#[IsGranted('ROLE_COMPTABLE')]
class ComptableController extends AbstractController
{
    // Route principale qui charge TOUT pour la page unique
    #[Route('/', name: 'app_comptable_dashboard', methods: ['GET'])]
    public function dashboard(
        Request $request,
        CommandeRepository $commandeRepo,
        PaiementRepository $paiementRepo,
        ArretDeCaisseRepository $arretDeCaisseRepo
    ): Response {
        // --- 1. Données pour le NOUVEAU tableau de bord (Slide 1) ---
        $filters = [
            'client' => $request->query->get('client'),
            'date_debut' => $request->query->get('date_debut'),
            'date_fin' => $request->query->get('date_fin'),
            'statut' => $request->query->get('statut'),
            'non_solde' => $request->query->get('non_solde'),
        ];
        $commandesData = $commandeRepo->findForComptableDashboard($filters);
        
        $totalAPayer = 0;
        $totalPaye = 0;
        $commandesView = [];
        foreach ($commandesData as $data) {
            /** @var Commande $commande */
            $commande = $data[0];
            $montantPaye = (float) $data['montantPaye'];
            // On utilise le total calculé par la requête, c'est plus performant
            $totalCommande = (float) $data['totalCommande']; 
            
            $resteAPayer = $totalCommande - $montantPaye;

            $totalAPayer += $totalCommande;
            $totalPaye += $montantPaye;
            
            $commandesView[] = [
                'commande' => $commande,
                'totalCommande' => $totalCommande, // On passe aussi le total à la vue
                'montantPaye' => $montantPaye,
                'resteAPayer' => $resteAPayer,
            ];
        }
        $paiementsGroupes = $paiementRepo->createQueryBuilder('p')->select('p.referencePaiement, SUM(p.montant) as total')->groupBy('p.referencePaiement')->getQuery()->getResult();
        $totauxParMoyen = [];
        foreach ($paiementsGroupes as $groupe) {
            $totauxParMoyen[$groupe['referencePaiement']] = $groupe['total'];
        }

        // --- 2. Données pour l'Arrêt de Caisse (Slide 2) ---
        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $totalsToClose = [];
        foreach ($totalsToCloseRaw as $row) {
            $totalsToClose[$row['referencePaiement']] = $row['totalTheorique'];
        }

        // --- 3. Données pour l'Historique (Slide 3) ---
        $pastClotures = $arretDeCaisseRepo->findBy([], ['dateCloture' => 'DESC'], 10);

        // --- On envoie TOUT à la vue unique ---
        return $this->render('comptable/index.html.twig', [
            // Données pour Slide 1
            'commandes' => $commandesView,
            'synthese' => [
                'totalAPayer' => $totalAPayer,
                'totalPaye' => $totalPaye,
                'resteGlobal' => $totalAPayer - $totalPaye,
                'parMoyen' => $totauxParMoyen
            ],
            'filters' => $filters,
            'statutsPossibles' => [Commande::STATUT_COMPTABLE_ATTENTE, Commande::STATUT_COMPTABLE_PARTIEL, Commande::STATUT_COMPTABLE_PAYE, Commande::STATUT_COMPTABLE_RECOUVREMENT],

            // Données pour Slide 2
            'hasPaymentsToClose' => !empty($totalsToClose),
            'totals' => $totalsToClose,

            // Données pour Slide 3
            'pastClotures' => $pastClotures,
        ]);
    }

    // --- Les ACTIONS restent les mêmes, elles redirigent vers le dashboard ---

    #[Route('/commande/{id}/verifier', name: 'app_comptable_commande_verifier', methods: ['POST'])]
    public function verifierCommande(Commande $commande, EntityManagerInterface $em, PaiementRepository $paiementRepo): Response
    {
        $commande->setVerifieComptable(true);
        $montantPaye = $paiementRepo->findTotalPayePourCommande($commande->getId());
        $reste = $commande->getTotal() - $montantPaye;

        if ($reste <= 0) $commande->setStatutComptable(Commande::STATUT_COMPTABLE_PAYE);
        elseif ($montantPaye > 0 && $reste > 0) $commande->setStatutComptable(Commande::STATUT_COMPTABLE_PARTIEL);
        else $commande->setStatutComptable(Commande::STATUT_COMPTABLE_ATTENTE);
        
        $em->flush();
        $this->addFlash('success', 'La commande #' . $commande->getId() . ' a été vérifiée.');
        return $this->redirectToRoute('app_comptable_dashboard');
    }

    #[Route('/commande/{id}/recouvrement', name: 'app_comptable_commande_recouvrement', methods: ['POST'])]
    public function recouvrementCommande(Commande $commande, EntityManagerInterface $em): Response
    {
        $commande->setStatutComptable(Commande::STATUT_COMPTABLE_RECOUVREMENT);
        $em->flush();
        $this->addFlash('warning', 'La commande #' . $commande->getId() . ' est passée en recouvrement.');
        return $this->redirectToRoute('app_comptable_dashboard');
    }

    #[Route('/commande/{id}/marquer-paye', name: 'app_comptable_commande_marquer_paye', methods: ['POST'])]
    public function marquerPayeCommande(Commande $commande, EntityManagerInterface $em): Response
    {
        $commande->setStatutComptable(Commande::STATUT_COMPTABLE_PAYE);
        $em->flush();
        $this->addFlash('success', 'La commande #' . $commande->getId() . ' a été marquée comme payée.');
        return $this->redirectToRoute('app_comptable_dashboard');
    }
    
    // Le détail reste une page dédiée pour plus de clarté
    #[Route('/commande/{id}', name: 'app_comptable_commande_detail', methods: ['GET'])]
    public function detailCommande(Commande $commande): Response
    {
        // Vous aurez besoin d'un fichier templates/comptable/detail_commande.html.twig
        return $this->render('comptable/detail_commande.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/arret-de-caisse/submit', name: 'app_comptable_arret_de_caisse_submit', methods: ['POST'])]
    public function clotureSubmit(Request $request, PaiementRepository $paiementRepo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $arretDeCaisse = new ArretDeCaisse($user);
        
        $fondInitial = (float) $request->request->get('fond_de_caisse_initial', 0);
        $arretDeCaisse->setFondDeCaisseInitial($fondInitial);

        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $details = [];

        foreach ($totalsToCloseRaw as $row) {
            $reference = $row['referencePaiement'];
            $theorique = (float) $row['totalTheorique'];
            $compte = 0;
            $billetage = [];

            if ($reference === 'Espèce') {
                $valeurs = [20000, 10000, 5000, 2000, 1000, 500, 200, 100];
                foreach ($valeurs as $val) {
                    $qte = (int) $request->request->get('billet_' . $val, 0);
                    if ($qte > 0) $billetage[$val] = $qte;
                    $compte += $val * $qte;
                }
                $ecart = $compte - ($theorique + $fondInitial);
                $details[$reference] = ['theorique' => $theorique, 'compte' => $compte, 'ecart' => $ecart, 'billetage' => $billetage];
            } else {
                $compte = (float) $request->request->get('compte_' . strtolower(str_replace(' ', '_', $reference)), 0);
                $ecart = $compte - $theorique;
                $details[$reference] = ['theorique' => $theorique, 'compte' => $compte, 'ecart' => $ecart];
            }
        }

        $arretDeCaisse->setDetailsPaiements($details);
        $arretDeCaisse->setNotes($request->request->get('notes', ''));

        $paiements = $paiementRepo->findBy(['arretDeCaisse' => null, 'statut' => 'effectué']);
        foreach ($paiements as $paiement) {
            $arretDeCaisse->addPaiement($paiement);
        }

        $em->persist($arretDeCaisse);
        $em->flush();

        $this->addFlash('success', 'La caisse a été clôturée avec succès.');
        return $this->redirectToRoute('app_comptable_dashboard');
    }

    #[Route('/paiement/{id}/recu', name: 'app_comptable_paiement_recu')]
    public function recuPaiement(Paiement $paiement): Response
    {
        // On s'assure qu'on ne génère pas de reçu pour un paiement non effectué
        if ($paiement->getStatut() !== 'effectué') {
            $this->addFlash('danger', 'Impossible de générer un reçu pour un paiement non effectué.');
            return $this->redirectToRoute('app_comptable_commande_detail', ['id' => $paiement->getCommande()->getId()]);
        }

        // 1. On rend la vue Twig en une chaîne de caractères HTML
        $html = $this->renderView('comptable/recu_paiement.html.twig', [
            'paiement' => $paiement,
        ]);
        
        // 2. On configure Dompdf
        $pdfOptions = new Options();
        // C'est utile pour que Dompdf puisse charger des images ou des CSS si besoin
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        // 3. On instancie Dompdf avec nos options
        $dompdf = new Dompdf($pdfOptions);

        // 4. On charge le HTML dans Dompdf
        $dompdf->loadHtml($html);

        // 5. On définit le format du papier (A4, portrait)
        $dompdf->setPaper('A4', 'portrait');

        // 6. On "rend" le HTML en PDF
        $dompdf->render();

        // 7. On envoie le PDF généré au navigateur.
        // Le nom du fichier sera 'recu-paiement-ID.pdf'
        // 'Attachment' => false permet de l'afficher dans le navigateur au lieu de le télécharger.
        $dompdf->stream(sprintf('recu-paiement-%s.pdf', $paiement->getId()), [
            "Attachment" => false
        ]);

        // On retourne une réponse vide car Dompdf gère lui-même l'envoi du fichier
        return new Response('', 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}