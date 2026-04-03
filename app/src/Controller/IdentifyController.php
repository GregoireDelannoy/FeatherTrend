<?php

namespace App\Controller;

use App\Form\IdentifyPictureFormType;
use App\Service\ImageService;
use Imagine\Gd\Imagine;
use OnnxRuntime\Model;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

define('MODEL_PATH', dirname(__DIR__).'/../ml_models/regnet_z_4g_eu-common.onnx');
define('MODEL_METADATA', dirname(__DIR__).'/../ml_models/regnet_z_4g_eu-common.onnx_metadata.json');
define('MODEL_INPUT_SIZE', 384);

function identifyPicture(string $picturePath, ImageService $imageService)
{
    $model = new Model(MODEL_PATH);
    $modelMetadata = json_decode(file_get_contents(MODEL_METADATA), true);

    $imagine = new Imagine();
    $photo = $imagine->open($picturePath);
    $boxed = $imageService->letterboxImage($photo, MODEL_INPUT_SIZE);
    $preprocessed = $imageService->preprocess($boxed);
    $results = $model->predict(['x' => $preprocessed]);
    $probabilities = $results['output'][0];

    $maxProbability = max($probabilities);
    $maxProbabilityIndex = array_search($maxProbability, $probabilities);
    $species = array_search($maxProbabilityIndex, $modelMetadata['class_to_idx']);

    return [
        'probability' => $maxProbability,
        'species' => $species,
        'imageThumbnail' => base64_encode($boxed->get('png')),
    ];
}

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

            $identification = identifyPicture($pictureData->getRealPath(), $imageService);

            return $this->render('identify_result.html.twig', $identification);
        }

        return $this->render('identify_form.html.twig', [
            'identifyPictureForm' => $form,
        ]);
    }
}
