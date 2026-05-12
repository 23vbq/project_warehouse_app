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
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class OperationLineType extends AbstractType
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly LocationRepository $locationRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $operationType = $options['operation_type'];

        $this->addEntityChoices($builder, $operationType, null);

        $builder->add('quantity', NumberType::class, [
            'scale' => OperationLine::QUANTITY_SCALE,
            'constraints' => [
                new NotBlank(message: 'Ilość jest wymagana.'),
                new Positive(message: 'Ilość musi być większa od zero.'),
            ],
        ]);

        if (Operation::TYPE_RELOCATION !== $operationType) {
            $builder->add('unitPrice', NumberType::class, [
                'required' => false,
                'scale' => OperationLine::PRICE_SCALE,
                'constraints' => [
                    new Positive(message: 'Cena musi być większa od zera.'),
                ],
            ]);
        }

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) use ($operationType): void {
                $line = $event->getData();
                if (!$line instanceof OperationLine || null === $line->getId()) {
                    return;
                }

                $this->addEntityChoices($event->getForm(), $operationType, [
                    'product' => $line->getProduct()?->getId(),
                    'locationFrom' => $line->getLocationFrom()?->getId(),
                    'locationTo' => $line->getLocationTo()?->getId(),
                ]);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            fn (FormEvent $event) => $this->addEntityChoices($event->getForm(), $operationType, $event->getData())
        );
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

    private function addEntityChoices(
        FormBuilderInterface|FormInterface $form,
        string $operationType,
        ?array $data,
    ): void {
        $productId = !empty($data['product']) ? (int) $data['product'] : null;
        $locationFromId = !empty($data['locationFrom']) ? (int) $data['locationFrom'] : null;
        $locationToId = !empty($data['locationTo']) ? (int) $data['locationTo'] : null;

        $form->add('product', EntityType::class, [
            'class' => Product::class,
            'choice_label' => fn (Product $p) => sprintf('[%s] %s', $p->getSku(), $p->getName()),
            'choice_loader' => new CallbackChoiceLoader(function () use ($productId): array {
                if (!$productId) {
                    return [];
                }
                $queryBuilder = $this->productRepository->createQueryBuilder('p')
                    ->where('p.id = :id')
                    ->setParameter('id', $productId);
                $this->productRepository->addActiveFilter($queryBuilder, 'p');

                return $queryBuilder->getQuery()->getResult();
            }),
            'placeholder' => 'Wybierz produkt...',
            'constraints' => [
                new NotBlank(message: 'Produkt jest wymagany.'),
            ],
        ]);

        if (in_array($operationType, [Operation::TYPE_RELEASE, Operation::TYPE_RELOCATION], true)) {
            $form->add('locationFrom', EntityType::class, [
                'class' => Location::class,
                'choice_label' => fn (Location $l) => $l->getCode().($l->getName() ? ' — '.$l->getName() : ''),
                'choice_loader' => new CallbackChoiceLoader(function () use ($locationFromId): array {
                    if (!$locationFromId) {
                        return [];
                    }
                    $queryBuilder = $this->locationRepository->createQueryBuilder('l')
                        ->where('l.id = :id')
                        ->setParameter('id', $locationFromId);
                    $this->locationRepository->addActiveFilter($queryBuilder, 'l');

                    return $queryBuilder->getQuery()->getResult();
                }),
                'placeholder' => 'Lokalizacja źródłowa...',
                'constraints' => [
                    new NotBlank(message: 'Lokalizacja źródłowa jest wymagana.'),
                ],
            ]);
        }

        if (in_array($operationType, [Operation::TYPE_RECEIPT, Operation::TYPE_RELOCATION], true)) {
            $form->add('locationTo', EntityType::class, [
                'class' => Location::class,
                'choice_label' => fn (Location $l) => $l->getCode().($l->getName() ? ' — '.$l->getName() : ''),
                'choice_loader' => new CallbackChoiceLoader(function () use ($locationToId): array {
                    if (!$locationToId) {
                        return [];
                    }
                    $queryBuilder = $this->locationRepository->createQueryBuilder('l')
                        ->where('l.id = :id')
                        ->setParameter('id', $locationToId);
                    $this->locationRepository->addActiveFilter($queryBuilder, 'l');

                    return $queryBuilder->getQuery()->getResult();
                }),
                'placeholder' => 'Lokalizacja docelowa...',
                'constraints' => [
                    new NotBlank(message: 'Lokalizacja docelowa jest wymagana.'),
                ],
            ]);
        }
    }
}
