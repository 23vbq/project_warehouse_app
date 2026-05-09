<?php

namespace App\Form;

use App\Entity\Operation;
use App\Entity\Relocation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class RelocationType extends AbstractType
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
            ->add('reason', TextType::class, [
                'required' => false,
            ])
            ->add('operationLines', CollectionType::class, [
                'entry_type' => OperationLineType::class,
                'entry_options' => ['operation_type' => Operation::TYPE_RELOCATION],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Relocation::class,
        ]);
    }
}
