<?php

namespace App\Repository;

use App\Entity\Species;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Species>
 */
class SpeciesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Species::class);
    }


    public function findAllWithPictureCount(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id, s.scientific_name, s.common_name, COUNT(p.id) as count')
            ->innerJoin('s.pictures', 'p')
            ->groupBy('s.id', 's.scientific_name', 's.common_name')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find all pictures for a species grouped by month across all years
     * Returns array indexed by month (1-12) with count and pictures
     */
    public function findMonthlyPicturesBySpecies(int $speciesId): array
    {
        $pictures = $this->createQueryBuilder('s')
            ->select('p.id, p.datetime')
            ->innerJoin('s.pictures', 'p')
            ->where('s.id = :speciesId')
            ->setParameter('speciesId', $speciesId)
            ->orderBy('p.datetime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        // Initialize 12-month array
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = [
                'count' => 0,
                'pictures' => []
            ];
        }

        // Group pictures by month
        foreach ($pictures as $picture) {
            if ($picture['datetime']) {
                $month = (int)$picture['datetime']->format('m');
                $monthlyData[$month]['pictures'][] = [
                    'id' => $picture['id'],
                    'datetime' => $picture['datetime']->format('c')
                ];
                $monthlyData[$month]['count']++;
            }
        }

        // Convert to sequential array
        return array_values($monthlyData);
    }
}
