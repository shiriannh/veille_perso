<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelpController extends AbstractController
{
    #[Route('/aide', name: 'app_help', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('help/index.html.twig');
    }
}
