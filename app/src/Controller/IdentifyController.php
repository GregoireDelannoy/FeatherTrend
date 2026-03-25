<?php
namespace App\Controller;

use App\Form\IdentifyPictureFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use OnnxRuntime\Model;

define('MODEL_PATH', dirname(__DIR__).'/../ml_models/regnet_z_4g_eu-common.onnx');
define('MODEL_METADATA', dirname(__DIR__).'/../ml_models/regnet_z_4g_eu-common.onnx_metadata.json');
define('MODEL_INPUT_SIZE', 384);

function letterboxImage(\Imagine\Image\ImageInterface $image, int $newSize): \Imagine\Image\ImageInterface
{
    $size = $image->getSize();
    $iw = $size->getWidth();
    $ih = $size->getHeight();

    $scale = min($newSize / $iw, $newSize / $ih);
    $nw = (int)($iw * $scale);
    $nh = (int)($ih * $scale);

    $image = $image->resize(new Box($nw, $nh), \Imagine\Image\ImageInterface::FILTER_CUBIC);

    $palette = new RGB();
    $imagine = new Imagine();
    $newImage = $imagine->create(new Box($newSize, $newSize), $palette->color([128, 128, 128]));

    $newImage->paste($image, new Point((int)(($newSize - $nw) / 2), (int)(($newSize - $nh) / 2)));

    return $newImage;
}

function preprocess(\Imagine\Image\ImageInterface $boxed): array
{
    $width  = $boxed->getSize()->getWidth();
    $height = $boxed->getSize()->getHeight();

    $channels = [[], [], []]; // R, G, B

    for ($x = 0; $x < $height; $x++) {
        // Add an column array for each passing line
        $channels[0][] = [];
        $channels[1][] = [];
        $channels[2][] = [];
        for ($y = 0; $y < $width; $y++) {
            $color = $boxed->getColorAt(new Point($x, $y));
            $channels[0][$x][] = $color->getValue(\Imagine\Image\Palette\Color\RGB::COLOR_RED)   / 255.0;
            $channels[1][$x][] = $color->getValue(\Imagine\Image\Palette\Color\RGB::COLOR_GREEN) / 255.0;
            $channels[2][$x][] = $color->getValue(\Imagine\Image\Palette\Color\RGB::COLOR_BLUE)  / 255.0;
        }
    }

    return [
        $channels
    ];
}

function identifyPicture(string $picturePath)
{
        $model = new Model(MODEL_PATH);
        $modelMetadata = json_decode(file_get_contents(MODEL_METADATA), TRUE);

        $imagine = new Imagine();
        $photo = $imagine->open($picturePath);
        $boxed = letterboxImage($photo, MODEL_INPUT_SIZE);
        $preprocessed = preprocess($boxed);
        $results = $model->predict(['x' => $preprocessed]);
        $probabilities = $results["output"][0];

        $maxProbability = max($probabilities);
        $maxProbabilityIndex = array_search($maxProbability, $probabilities);
        $specie = array_search($maxProbabilityIndex, $modelMetadata["class_to_idx"]);

        return [
            "probability" => $maxProbability,
            "specie" => $specie,
            "imageThumbnail" => base64_encode($boxed->get("png")),
        ];
}

class IdentifyController extends AbstractController
{
    #[Route(path: '/identify', name: 'app_identify')]
    public function identify(Request $request): Response
    {
        $form = $this->createForm(IdentifyPictureFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pictureData = $form->get('picture')->getData();

            $identification = identifyPicture($pictureData->getRealPath());

            return $this->render('identify_result.html.twig', $identification);
        }

        return $this->render('identify_form.html.twig', [
            'identifyPictureForm' => $form,
        ]);
    }
}
