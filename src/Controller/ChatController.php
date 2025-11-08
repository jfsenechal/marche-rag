<?php

namespace App\Controller;

use App\Entity\Discussion;
use App\Repository\DiscussionRepository;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly DiscussionRepository $discussionRepository,
        #[Autowire('%env(DOCUMENTATION_URL)%')]
        private readonly string $documentationUrl,
    ) {
    }

    #[Route('/', name: 'chat_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $discussions = $this->discussionRepository->findAll();

        return $this->render('chat/index.html.twig', [
            'discussions' => $discussions,
        ]);
    }

    #[Route('/new', name: 'chat_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $discussion = new Discussion();
        $this->discussionRepository->insert($discussion);

        return $this->redirectToRoute('chat_show', ['id' => $discussion->id]);
    }

    #[Route('/show/{id}', name: 'chat_show', methods: ['GET','POST'])]
    public function show(Discussion $discussion): Response
    {
        return $this->render('chat/new.html.twig', [
            'url' => $this->documentationUrl,
            'discussion' => $discussion,
        ]);
    }

    #[Route('/reset/{id}', name: 'chat_reset', methods: ['GET'])]
    public function chatReset(Discussion $discussion): Response
    {
        $this->messageRepository->removeAllByDiscussion($discussion);
        $this->discussionRepository->remove($discussion);
        $this->discussionRepository->flush();

        $this->addFlash('success', 'Discussion supprimée');

        return $this->redirectToRoute('rag_index');
    }
}
