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

    #[Route('/documents/{type}', name: 'documents_index', methods: ['GET'])]
    public function docs(Request $request, string $type = 'all'): Response
    {
        if ($type === 'all') {
            $documents = $this->documentRepository->findAll();
        } else {
            $documents = $this->documentRepository->findByType($type);
        }

        return $this->render('data/documents.html.twig', [
            'documents' => $documents,
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
