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
                    new NotBlank(),
                    new Length(max: 50),
                ],
            ])
            ->add('ean', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(max: 13),
                ],
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => ProductTypeEnum::class,
                'choice_label' => fn(ProductTypeEnum $t) => $t->label(),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('unit', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 20),
                ],
            ])
            ->add('unitPrice', NumberType::class, [
                'scale' => 2,
                'constraints' => [
                    new NotBlank(),
                    new Positive(),
                ],
            ])
            ->add('minStockLevel', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new Positive(),
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
