<?php

namespace App\Twig\Components;

use App\Entity\Discussion;
use App\Entity\Message;
use App\Form\ChatMessageType;
use App\OpenAI\Client;
use App\Repository\MessageRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Chat', template: 'components/Chat.html.twig')]
class Chat extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp()]
    public Discussion $discussion;

    public function __construct(
        private readonly Client $client,
        private readonly MessageRepository $messageRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function hasValidationErrors(): bool
    {
        return $this->getForm()->isSubmitted() && !$this->getForm()->isValid();
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messageRepository->findLatest($this->discussion);
    }

    #[LiveAction]
    public function submit(): void
    {
        $this->submitForm();

        $userPrompt = $this->getForm()->get('userPrompt')->getData();

        // Generate a conversation title from the first message
        $isFirstMessage = count($this->discussion->messages) === 0;
        if ($isFirstMessage) {
            $this->discussion->name = $this->client->generateConversationTitle($userPrompt);
        }

        $message = new Message($userPrompt, true, $this->discussion);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $embeddings = $this->client->getEmbeddings($userPrompt);
        $documents = $this->documentRepository->findNearest($embeddings);
        $messages = $this->messageRepository->findLatest($this->discussion);
        $answer = $this->client->getAnswer($documents, $messages);

        $message = new Message($answer, false, $this->discussion);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->resetForm();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ChatMessageType::class);
    }
}
