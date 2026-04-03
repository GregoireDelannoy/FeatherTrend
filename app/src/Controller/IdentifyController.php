<?php

namespace App\Controller;

use App\Form\IdentifyPictureFormType;
use App\Service\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IdentifyController extends AbstractController
{
    #[Route(path: '/identify', name: 'app_identify')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function identify(Request $request, ImageService $imageService, LoggerInterface $logger): Response
    {
        $form = $this->createForm(IdentifyPictureFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $logger->error('/identify Form validation error: '.$error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $pictureData = $form->get('picture')->getData();

            $identification = $imageService::identifyPicture($pictureData->getRealPath(), $imageService, $logger);

            return $this->render('identify_result.html.twig', $identification);
        }

        return $this->render('identify_form.html.twig', [
            'identifyPictureForm' => $form,
        ]);
    }
}
