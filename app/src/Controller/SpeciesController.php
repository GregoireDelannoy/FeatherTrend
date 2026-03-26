<?php

namespace App\Controller;

use App\Entity\Species;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SpeciesController extends AbstractController
{
    // Return the list of species that are present in the dataset with picture counts
    #[Route(path: '/species', methods: ['GET'])]
    public function getSpecies(EntityManagerInterface $entityManager): Response
    {
        $species = $entityManager->getRepository(className: Species::class)->findAllWithPictureCount();

        // Transform the result into the desired JSON format
        $result = array_map(function ($item) {
            return [
                'id' => $item['id'],
                'scientific_name' => $item['scientific_name'],
                'common_name' => $item['common_name'],
                'count' => (int) $item['count'],
            ];
        }, $species);

        return new JsonResponse($result);
    }

    // Return monthly breakdown of pictures for a species (grouped across all years)
    #[Route(path: '/species/{id}/monthly', methods: ['GET'])]
    public function getSpeciesMonthly(int $id, EntityManagerInterface $entityManager): Response
    {
        $monthlyData = $entityManager->getRepository(className: Species::class)->findMonthlyPicturesBySpecies($id);

        return new JsonResponse($monthlyData);
    }
}
