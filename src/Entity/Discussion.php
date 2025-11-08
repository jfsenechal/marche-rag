<?php

namespace App\Entity;

use App\Repository\DiscussionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiscussionRepository::class)]
class Discussion
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'string', nullable: false)]
    public readonly string $id;

    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $name = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'discussion', cascade: ['persist', 'remove'])]
    public Collection $messages;

    public function __construct()
    {
        $this->id = uuid_create();
        $this->messages = new ArrayCollection();
    }
}
