<?php

namespace App\Repository;

use App\Doctrine\OrmCrudTrait;
use App\Entity\Discussion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Discussion>
 */
class DiscussionRepository extends ServiceEntityRepository
{
    use OrmCrudTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Discussion::class);
    }

    public function removeAll():void
    {
        foreach ($this->findAll() as $message) {
            $this->remove($message);
        }
        $this->flush();
    }
}
