<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\TimestampableInterface;
use Knp\DoctrineBehaviors\Model\Timestampable\TimestampableTrait;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'string', nullable: false)]
    public readonly string $id;

    #[ORM\ManyToOne(targetEntity: Discussion::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public readonly Discussion $discussion;

    public function __construct(
        #[ORM\Column(type: 'text')]
        public readonly string $content,
        #[ORM\Column(type: 'boolean')]
        public readonly bool $isMe,
        Discussion $discussion,
    ) {
        $this->id = uuid_create();
        $this->discussion = $discussion;
    }
}
