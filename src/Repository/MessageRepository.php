<?php

namespace App\Repository;

use App\Doctrine\OrmCrudTrait;
use App\Entity\Discussion;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    use OrmCrudTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findLatest(Discussion $discussion): array
    {
        $latest = $this->createQueryBuilder('m')
            ->where('m.discussion = :discussion')
            ->setParameter('discussion', $discussion)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        usort($latest, function (Message $a, Message $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        return $latest;
    }

    public function removeAllByDiscussion(Discussion $discussion):void
    {
        foreach ($discussion->messages as $message) {
            $this->remove($message);
        }
        $this->flush();
    }
}
