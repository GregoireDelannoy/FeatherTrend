<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class IdentifyPictureFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('picture', FileType::class, [
                'label' => 'Picture to identify',

                // unmapped means that this field is not associated to any entity property
                'mapped' => false,
                'required' => true,

                // unmapped fields can't define their validation using attributes
                // in the associated entity, so you can use the PHP constraint classes
                'constraints' => [
                    new Assert\File(
                        maxSize: '40M',
                        extensions: ['jpg', 'jpeg', 'png', 'webp'],
                        extensionsMessage: 'Please upload a valid image document (jpg/png/webp of less than 40MB)',
                    ),
                ],
            ])
        ;
    }
}
