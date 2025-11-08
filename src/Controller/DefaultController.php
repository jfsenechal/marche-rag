<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'rag_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('default/index.html.twig', [

        ]);
    }
    #[Route('/documentation', name: 'rag_documentation', methods: ['GET'])]
    public function documentation(Request $request): Response
    {
        return $this->render('default/documentation.html.twig', [

        ]);
    }
}
