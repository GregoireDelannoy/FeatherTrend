<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;

use App\Entity\Pictures;

class PicturesController extends AbstractController
{
    private const THUMBNAIL_SIZE = 200;
    private function getThumbnailPath(int $id, string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        return sprintf(
            '%s/feathertrend_thumbnails/%d_%s.%s',
            sys_get_temp_dir(),
            $id,
            $pathInfo['filename'],
            $pathInfo['extension']
        );
    }

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

    #[Route(path: '/pictures/{id}/thumbnail', methods: ['GET'])]
    public function getThumbnail(int $id, EntityManagerInterface $entityManager): Response
    {
        $picture = $entityManager->getRepository(className: Pictures::class)->find($id);

        if (!$picture) {
            return new Response('Picture not found', Response::HTTP_NOT_FOUND);
        }

        $picturePath = $picture->getPath();
        $thumbnailPath = $this->getThumbnailPath($id, $picturePath);
        $thumbnailDirname = pathinfo($thumbnailPath)["dirname"];

        if (!file_exists($picturePath)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        if (!file_exists($thumbnailDirname)){
            mkdir($thumbnailDirname);
        }

        if (!file_exists($thumbnailPath)) {
            $imagine = new Imagine();
            list($width, $height) = getimagesize($picturePath);
            $ratio = $width/$height;
            if ($width > $height) {
                $thumbnailWidth = self::THUMBNAIL_SIZE;
                $thumbnailHeight = floor(self::THUMBNAIL_SIZE / $ratio);
            } else {
                $thumbnailWidth = floor(self::THUMBNAIL_SIZE * $ratio);
                $thumbnailHeight = self::THUMBNAIL_SIZE;
            }

            $photo = $imagine->open($picturePath);
            $photo->resize(new Box($thumbnailWidth, $thumbnailHeight))->save($thumbnailPath);
        }

        return new BinaryFileResponse($thumbnailPath);
    }
}
