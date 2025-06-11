<?php

namespace App\Controller;

use App\Entity\Depense;
use App\Entity\Revenu;
use App\Form\DepenseType;
use App\Form\RevenuType;
use App\Repository\DepenseRepository;
use App\Repository\RevenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Dompdf\Dompdf;
use Dompdf\Options;

class ComptableController extends AbstractController
{
    private function getCommonData(DepenseRepository $depenseRepo, RevenuRepository $revenuRepo, Security $security): array
    {
        $user = $security->getUser();
        $depenses = $depenseRepo->findAll();
        $revenus = $revenuRepo->findAll();
        $totalDepenses = $depenseRepo->getTotalAmount();
        $totalRevenus = $revenuRepo->getTotalAmount();
        $benefice = $totalRevenus - $totalDepenses;

        // Create forms for expense and revenue
        $depense = new Depense();
        $revenu = new Revenu();
        $depenseForm = $this->createForm(DepenseType::class, $depense);
        $revenuForm = $this->createForm(RevenuType::class, $revenu);

        return [
            'user' => $user,
            'depenses' => $depenses,
            'revenus' => $revenus,
            'totalDepenses' => $totalDepenses,
            'totalRevenus' => $totalRevenus,
            'benefice' => $benefice,
            'depense_form' => $depenseForm->createView(),
            'revenu_form' => $revenuForm->createView(),
        ];
    }

    #[Route('/comptable', name: 'app_comptable')]
    #[Route('/depenses', name: 'app_comptable_depenses')]
    #[Route('/revenus', name: 'app_comptable_revenus')]
    #[Route('/resume', name: 'app_comptable_resume')]
    public function index(DepenseRepository $depenseRepo, RevenuRepository $revenuRepo, Security $security): Response
    {
        return $this->render('comptable/index.html.twig', $this->getCommonData($depenseRepo, $revenuRepo, $security));
    }

    #[Route('/depense/ajouter', name: 'app_comptable_depense_ajouter', methods: ['GET', 'POST'])]
    public function ajouterDepense(Request $request, EntityManagerInterface $em, DepenseRepository $depenseRepo, RevenuRepository $revenuRepo, Security $security): Response
    {
        $depense = new Depense();
        $form = $this->createForm(DepenseType::class, $depense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($depense);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Dépense ajoutée avec succès.']);
            }

            $this->addFlash('success', 'Dépense ajoutée avec succès.');
            return $this->redirectToRoute('app_comptable_depenses');
        }

        $data = $this->getCommonData($depenseRepo, $revenuRepo, $security);
        $data['depense_form'] = $form->createView();

        return $this->render('comptable/index.html.twig', $data);
    }

    #[Route('/depense/{id}/modifier', name: 'app_comptable_depense_modifier', methods: ['GET', 'POST'])]
    public function modifierDepense(Depense $depense, Request $request, EntityManagerInterface $em, DepenseRepository $depenseRepo, RevenuRepository $revenuRepo, Security $security): Response
    {
        $form = $this->createForm(DepenseType::class, $depense);
        $form->handleRequest($request->isRequest($request));

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Dépense modifiée avec succès.']);
            }

            $this->addFlash('success', 'Dépense modifiée avec succès.');
            return $this->redirectToRoute('app_comptable_depenses');
        }

        $data = $this->getCommonData($depenseRepo, $revenuRepo, $security);
        $data['depense_form'] = $dataform->createView();

        return $this->render('comptable/index.html.twig', $data);
    }

    #[Route('/depense/{id}/supprimer', name: 'app_comptable_depense_supprimer')]
    public function supprimerDepense(Depense $depense, EntityManagerInterface $em): Response
    {
        $em->remove($depense);
        $em->flush();
        $this->addFlash('success', 'Dépense supprimée avec succès.');
        return $this->redirectToRoute('app_comptable_depenses');
    }

    #[Route('/revenu/ues', name: 'app_comptable_revenus_ajouter', methods: ['GET', 'POST'])]
    public function ajouterRevenu(Request $request, EntityManagerInterface $em, DepenseRepository $depenseRepo, RevenuRepository $revenuRepo, Security $security): Response
    {
        $revenu = new Revenu();
        $form = $this->createForm(RevenuType::class, $revenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($revenu);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Revenu ajouté avec succès.']);
            }

            $this->addFlash('success', 'Revenu ajouté avec succès.');
            return $this->redirectToRoute('app_comptable_revenus');
        }

        $data = $this->getCommonData($depenseRepo, $revenuRepo, $security);
        $data['revenu_form'] = $form->createView();

        return $this->render('comptable/index.html.twig', $data);
    }

    #[Route('/revenu/{id}/modifier', name: 'app_comptable_revenus_modifier', methods: ['GET', 'POST'])]
    public function modifierRevenu(Revenu $revenu, Request $request, EntityManagerInterface $em, DepenseRepository $depenseRepo, RevenuRepository $revenuRepo, Security $security): Response
    {
        $form = $this->createForm(RevenuType::class, $revenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Revenu modifié avec succès.']);
            }

            $this->addFlash('success', 'Revenu modifié avec succès.');
            return $this->redirectToRoute('app_comptable_revenus');
        }

        $data = $this->getCommonData($depenseRepo, $revenuRepo, $security);
        $data['revenu_form'] = $form->createView();

        return $this->render('comptable/index.html.twig', $data);
    }

    #[Route('/revenu/{id}/supprimer', name: 'app_comptable_revenus_supprimer')]
    public function supprimerRevenu(Revenu $revenu, EntityManagerInterface $em): Response
    {
        $em->remove($revenu);
        $em->flush();
        $this->addFlash('success', 'Revenu supprimé avec succès.');
        return $this->redirectToRoute('app_comptable_revenus');
    }

    #[Route('/export/excel', name: 'app_comptable_export_excel')]
    public function exportExcel(DepenseRepository $depenseRepo, RevenuRepository $revenuRepo): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comptabilité');

        $sheet->setCellValue('A1', 'Type');
        $sheet->setCellValue('B1', 'Montant');
        $sheet->setCellValue('C1', 'Description');

        $row = 2;

        foreach ($depenseRepo->findAll() as $depense) {
            $sheet->setCellValue('A' . $row, 'Dépense');
            $sheet->setCellValue('B' . $row, $depense->getMontant());
            $sheet->setCellValue('C' . $row, $depense->getDescription());
            $row++;
        }

        foreach ($revenuRepo->findAll() as $revenu) {
            $sheet->setCellValue('A' . $row, 'Revenu');
            $sheet->setCellValue('B' . $row, $revenu->getMontant());
            $sheet->setCellValue('C' . $row, $revenu->getDescription());
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'comptabilite.xlsx';
        $temp_file = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($temp_file);

        return $this->file($temp_file, $filename, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/export/pdf', name: 'app_comptable_export_pdf')]
    public function exportPdf(DepenseRepository $depenseRepo, RevenuRepository $revenuRepo): Response
    {
        $depenses = $depenseRepo->findAll();
        $revenus = $revenuRepo->findAll();
        $totalDepenses = $depenseRepo->getTotalAmount();
        $totalRevenus = $revenuRepo->getTotalAmount();
        $benefice = $totalRevenus - $totalDepenses;

        // Configuration de Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        // Rendu du contenu HTML à partir d'un template Twig
        $html = $this->renderView('comptable/pdf/export_pdf.html.twig', [
            'depenses' => $depenses,
            'revenus' => $revenus,
            'totalDepenses' => $totalDepenses,
            'totalRevenus' => $totalRevenus,
            'benefice' => $benefice,
        ]);

        // Génération du PDF
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Retourne le PDF en réponse
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="comptabilite.pdf"',
            ]
        );
    }

}