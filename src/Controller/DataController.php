<?php

namespace App\Controller;

use App\Repository\DocumentRepository;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/data', methods: ['GET'])]
class DataController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly MessageRepository $messageRepository,
    ) {
    }

    #[Route('/docs', name: 'docs_index', methods: ['GET'])]
    public function docs(Request $request): Response
    {
        return $this->render('data/docs.html.twig', [
            'documents' => $this->documentRepository->findAll(),
        ]);
    }

    #[Route('/messages', name: 'messages_index', methods: ['GET'])]
    public function messages(Request $request): Response
    {
        return $this->render('data/messages.html.twig', [
            'messages' => $this->messageRepository->findAll(),
        ]);
    }
}
