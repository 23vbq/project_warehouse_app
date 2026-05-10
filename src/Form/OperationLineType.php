<?php

namespace App\Form;

use App\Entity\Location;
use App\Entity\Operation;
use App\Entity\OperationLine;
use App\Entity\Product;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class OperationLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $operationType = $options['operation_type'];
        $data = $builder->getData();

        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => fn (Product $p) => sprintf('[%s] %s', $p->getSku(), $p->getName()),
                'query_builder' => function (ProductRepository $productRepository) use ($data) {
                    $qb = $productRepository->createQueryBuilder('p');

                    if ($data && $data->getProduct()) {
                        $qb->where('p.id = :productId')
                            ->setParameter('productId', $data->getProduct()->getId());
                    } else {
                        $qb->where('1 = 0');
                    }

                    $productRepository->addActiveFilter($qb, 'p');

                    return $qb;
                },
                'placeholder' => 'Wybierz produkt...',
                'constraints' => [
                    new NotBlank(message: 'Produkt jest wymagany.'),
                ],
            ]);

        if (in_array($operationType, [Operation::TYPE_RELEASE, Operation::TYPE_RELOCATION], true)) {
            $builder->add('locationFrom', EntityType::class, [
                'class' => Location::class,
                'choice_label' => fn (Location $l) => $l->getCode().($l->getName() ? ' — '.$l->getName() : ''),
                'query_builder' => function (LocationRepository $r) use ($data) {
                    $qb = $r->createQueryBuilder('l');

                    if ($data && $data->getLocationFrom()) {
                        $qb->where('l.id = :locationId')
                            ->setParameter('locationId', $data->getLocationFrom()->getId());
                    } else {
                        $qb->where('1 = 0');
                    }

                    $r->addActiveFilter($qb, 'l');

                    return $qb;
                },
                'placeholder' => 'Lokalizacja źródłowa...',
                'constraints' => [
                    new NotBlank(message: 'Lokalizacja źródłowa jest wymagana.'),
                ],
            ]);
        }

        if (in_array($operationType, [Operation::TYPE_RECEIPT, Operation::TYPE_RELOCATION], true)) {
            $builder->add('locationTo', EntityType::class, [
                'class' => Location::class,
                'choice_label' => fn (Location $l) => $l->getCode().($l->getName() ? ' — '.$l->getName() : ''),
                'query_builder' => function (LocationRepository $r) use ($data) {
                    $qb = $r->createQueryBuilder('l');

                    if ($data && $data->getLocationTo()) {
                        $qb->where('l.id = :locationId')
                            ->setParameter('locationId', $data->getLocationTo()->getId());
                    } else {
                        $qb->where('1 = 0');
                    }

                    $r->addActiveFilter($qb, 'l');

                    return $qb;
                },
                'placeholder' => 'Lokalizacja docelowa...',
                'constraints' => [
                    new NotBlank(message: 'Lokalizacja docelowa jest wymagana.'),
                ],
            ]);
        }

        $builder
            ->add('quantity', NumberType::class, [
                'scale' => 3,
                'constraints' => [
                    new NotBlank(message: 'Ilość jest wymagana.'),
                    new Positive(message: 'Ilość musi być większa od zera.'),
                ],
            ]);

        if (Operation::TYPE_RELOCATION !== $operationType) {
            $builder->add('unitPrice', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new Positive(message: 'Cena musi być większa od zera.'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperationLine::class,
        ]);

        $resolver->setRequired('operation_type');
        $resolver->setAllowedValues('operation_type', [
            Operation::TYPE_RECEIPT,
            Operation::TYPE_RELEASE,
            Operation::TYPE_RELOCATION,
        ]);
    }
}
