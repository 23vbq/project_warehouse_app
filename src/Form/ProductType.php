<?php

namespace App\Form;

use App\Entity\Product;
use App\Enum\ProductType as ProductTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sku', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'SKU jest wymagany.'),
                    new Length(max: 50, maxMessage: 'SKU może mieć maksymalnie {{ limit }} znaków.'),
                ],
            ])
            ->add('ean', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(max: 13, maxMessage: 'EAN może mieć maksymalnie {{ limit }} znaków.'),
                ],
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Nazwa jest wymagana.'),
                    new Length(max: 255, maxMessage: 'Nazwa może mieć maksymalnie {{ limit }} znaków.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => ProductTypeEnum::class,
                'choice_label' => fn (ProductTypeEnum $t) => $t->label(),
                'constraints' => [
                    new NotBlank(message: 'Wybierz kategorię.'),
                ],
            ])
            ->add('unit', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Jednostka miary jest wymagana.'),
                    new Length(max: 20, maxMessage: 'Jednostka może mieć maksymalnie {{ limit }} znaków.'),
                ],
            ])
            ->add('unitPrice', NumberType::class, [
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'Cena jest wymagana.'),
                    new Positive(message: 'Cena musi być większa od zera.'),
                ],
            ])
            ->add('minStockLevel', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new Positive(message: 'Minimalny stan musi być większy od zera.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
