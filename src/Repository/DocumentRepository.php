<?php

namespace App\Repository;

use App\Doctrine\OrmCrudTrait;
use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    use OrmCrudTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * @param float[] $embeddings
     *
     * @return Document[]
     */
    public function findNearest(array $embeddings): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.content IS NOT NULL AND s.content != \'\'')
            ->orderBy('cosine_similarity(s.embeddings, :embeddings)', 'DESC')
            ->setParameter('embeddings', $embeddings, 'vector')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findByReferenceId(string $referenceId): ?Document
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.referenceId = :id')
            ->setParameter('id', $referenceId, 'string')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function removeAll():void
    {
        foreach ($this->findAll() as $message) {
            $this->remove($message);
        }
        $this->flush();
    }
}
