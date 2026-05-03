<?php

namespace App\Controller;

use App\Repository\ImportRunRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/imports')]
class ImportRunController extends AbstractController
{
    #[Route('', name: 'app_import_run_index', methods: ['GET'])]
    public function index(ImportRunRepository $importRunRepository): Response
    {
        return $this->render('import_run/index.html.twig', [
            'runs' => $importRunRepository->findLatest(),
        ]);
    }
}
