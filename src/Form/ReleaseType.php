<?php

namespace App\Form;

use App\Entity\Operation;
use App\Entity\Release;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReleaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('documentDate', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(message: 'Data dokumentu jest wymagana.'),
                ],
            ])
            ->add('recipient', TextType::class, [
                'required' => false,
            ])
            ->add('customerOrderNumber', TextType::class, [
                'required' => false,
            ])
            ->add('releaseDate', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('releaseMethod', TextType::class, [
                'required' => false,
            ])
            ->add('operationLines', CollectionType::class, [
                'entry_type' => OperationLineType::class,
                'entry_options' => ['operation_type' => Operation::TYPE_RELEASE],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'constraints' => [
                    new Count(min: 1, minMessage: 'Dodaj przynajmniej jedną pozycję.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Release::class,
        ]);
    }
}
