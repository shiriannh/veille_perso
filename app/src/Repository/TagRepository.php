<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findOneBySlug(string $slug): ?Tag
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return Tag[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('tag')
            ->leftJoin('tag.mediaType', 'mediaType')
            ->addSelect('mediaType')
            ->orderBy('tag.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
