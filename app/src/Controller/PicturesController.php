<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Pictures;

class PicturesController extends AbstractController
{
    // Return a picture file by ID
    #[Route(path: '/pictures/{id}', methods: ['GET'])]
    public function getPicture(int $id, EntityManagerInterface $entityManager): Response
    {
        $picture = $entityManager->getRepository(className: Pictures::class)->find($id);

        if (!$picture) {
            return new Response('Picture not found', Response::HTTP_NOT_FOUND);
        }

        $filePath = $picture->getPath();

        if (!file_exists($filePath)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($filePath);
    }
}
