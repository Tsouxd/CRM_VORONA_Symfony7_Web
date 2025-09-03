<?php
namespace App\Controller;

use App\Entity\ArretDeCaisse;
use App\Entity\User;
use App\Repository\PaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comptable/arret-de-caisse')]
#[IsGranted('ROLE_COMPTABLE')]
class ArretDeCaisseController extends AbstractController
{
    #[Route('/', name: 'app_arret_de_caisse_index', methods: ['GET', 'POST'])]
    public function index(Request $request, PaiementRepository $paiementRepo, EntityManagerInterface $em): Response
    {
        $totalsToCloseRaw = $paiementRepo->findTotalsToClose();
        $totalsToClose = [];
        foreach ($totalsToCloseRaw as $row) {
            $totalsToClose[$row['methode']] = $row['totalTheorique'];
        }

        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();
            $arretDeCaisse = new ArretDeCaisse($user);
            
            $fondInitial = (float) $request->request->get('fond_de_caisse_initial', 0);
            $arretDeCaisse->setFondDeCaisseInitial($fondInitial);
            
            $details = [];
            foreach ($totalsToClose as $methode => $theorique) {
                $compte = (float) $request->request->get('compte_' . strtolower(str_replace(' ', '_', $methode)), 0);
                $details[$methode] = [ 'theorique' => $theorique, 'compte' => $compte, 'ecart' => $compte - $theorique ];
            }
            $arretDeCaisse->setDetailsPaiements($details);
            $arretDeCaisse->setNotes($request->request->get('notes'));

            $paiements = $paiementRepo->findBy(['arretDeCaisse' => null, 'statut' => 'effectué']);
            foreach ($paiements as $paiement) {
                $paiement->setArretDeCaisse($arretDeCaisse);
            }

            $em->persist($arretDeCaisse);
            $em->flush();

            $this->addFlash('success', 'La caisse a été clôturée avec succès.');
            return $this->redirectToRoute('app_comptable'); // Redirige vers la page d'accueil comptable
        }

        return $this->render('arret_de_caisse/index.html.twig', [
            'totals' => $totalsToClose,
        ]);
    }
}