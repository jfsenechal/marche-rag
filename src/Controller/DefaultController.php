<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'rag_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('default/index.html.twig', [

        ]);
    }
}
