<?php

namespace App\DataFixtures;

use App\Entity\Location;
use App\Entity\OperationLine;
use App\Entity\Product;
use App\Entity\Receipt;
use App\Entity\Release;
use App\Entity\Relocation;
use App\Entity\User;
use App\Service\OperationService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OperationFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly OperationService $operationService,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            LocationFixtures::class,
            ProductFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadReceipts($manager);
        $this->loadRelocations($manager);
        $this->loadReleases($manager);
    }

    private function loadReceipts(ObjectManager $manager): void
    {
        $pz1 = (new Receipt())
            ->setSupplier('Aluminium Polska Sp. z o.o.')
            ->setInvoiceNumber('FV/2026/04/0312')
            ->setDocumentDate(new \DateTimeImmutable('-30 days'))
            ->setCreatedBy($this->manager());

        $pz1->addOperationLine($this->line('SKU-RAW-001', 'A-01', null, '800.000', '22.50'));
        $pz1->addOperationLine($this->line('SKU-RAW-002', 'A-02', null, '150.000', '38.00'));
        $pz1->addOperationLine($this->line('SKU-RAW-003', 'A-03', null, '80.000', '45.00'));

        $manager->persist($pz1);
        $this->operationService->generateNumber($pz1);
        $this->operationService->confirm($pz1, $this->manager());

        $pz2 = (new Receipt())
            ->setSupplier('MetalParts GmbH')
            ->setInvoiceNumber('MP-2026-1847')
            ->setDocumentDate(new \DateTimeImmutable('-21 days'))
            ->setCreatedBy($this->employee());

        $pz2->addOperationLine($this->line('SKU-CON-001', 'A-04', null, '40.000', '18.00'));
        $pz2->addOperationLine($this->line('SKU-CON-002', 'A-05', null, '60.000', '12.00'));
        $pz2->addOperationLine($this->line('SKU-CON-003', 'A-05', null, '100.000', '8.50'));
        $pz2->addOperationLine($this->line('SKU-CON-004', 'A-06', null, '30.000', '25.00'));

        $manager->persist($pz2);
        $this->operationService->generateNumber($pz2);
        $this->operationService->confirm($pz2, $this->manager());

        $pz3 = (new Receipt())
            ->setSupplier('Aluminium Polska Sp. z o.o.')
            ->setInvoiceNumber('FV/2026/05/0089')
            ->setDocumentDate(new \DateTimeImmutable('-14 days'))
            ->setCreatedBy($this->employee());

        $pz3->addOperationLine($this->line('SKU-SEM-001', 'B-01', null, '50.000', '180.00'));
        $pz3->addOperationLine($this->line('SKU-SEM-002', 'B-02', null, '30.000', '210.00'));
        $pz3->addOperationLine($this->line('SKU-FIN-001', 'C-01', null, '40.000', '450.00'));
        $pz3->addOperationLine($this->line('SKU-FIN-002', 'C-02', null, '20.000', '520.00'));
        $pz3->addOperationLine($this->line('SKU-FIN-003', 'C-03', null, '35.000', '380.00'));

        $manager->persist($pz3);
        $this->operationService->generateNumber($pz3);
        $this->operationService->confirm($pz3, $this->manager());
    }

    private function loadRelocations(ObjectManager $manager): void
    {
        $mm1 = (new Relocation())
            ->setReason('Przekazanie do obróbki CNC — II etap')
            ->setDocumentDate(new \DateTimeImmutable('-10 days'))
            ->setCreatedBy($this->manager());

        $mm1->addOperationLine($this->line('SKU-SEM-001', 'B-03', 'B-01', '15.000'));
        $mm1->addOperationLine($this->line('SKU-SEM-002', 'B-04', 'B-02', '10.000'));

        $manager->persist($mm1);
        $this->operationService->generateNumber($mm1);
        $this->operationService->confirm($mm1, $this->manager());
    }

    private function loadReleases(ObjectManager $manager): void
    {
        $wz1 = (new Release())
            ->setRecipient('AutoWheels Sp. z o.o.')
            ->setCustomerOrderNumber('AW-2026-0541')
            ->setReleaseDate(new \DateTimeImmutable('-7 days'))
            ->setDocumentDate(new \DateTimeImmutable('-7 days'))
            ->setCreatedBy($this->employee());

        $wz1->addOperationLine($this->line('SKU-FIN-001', null, 'C-01', '12.000', '450.00'));
        $wz1->addOperationLine($this->line('SKU-FIN-002', null, 'C-02', '8.000', '520.00'));

        $manager->persist($wz1);
        $this->operationService->generateNumber($wz1);
        $this->operationService->confirm($wz1, $this->manager());

        $wz2 = (new Release())
            ->setRecipient('RimShop Warszawa')
            ->setCustomerOrderNumber('RS-0892/2026')
            ->setReleaseDate(new \DateTimeImmutable('-3 days'))
            ->setDocumentDate(new \DateTimeImmutable('-3 days'))
            ->setCreatedBy($this->employee());

        $wz2->addOperationLine($this->line('SKU-FIN-003', null, 'C-03', '10.000', '380.00'));
        $wz2->addOperationLine($this->line('SKU-FIN-001', null, 'C-01', '5.000', '450.00'));

        $manager->persist($wz2);
        $this->operationService->generateNumber($wz2);
        $this->operationService->confirm($wz2, $this->manager());
    }

    private function line(string $sku, ?string $locationTo, ?string $locationFrom, string $quantity, ?string $unitPrice = null): OperationLine
    {
        $line = (new OperationLine())
            ->setProduct($this->getReference('product_'.$sku, Product::class))
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice);

        if (null !== $locationTo) {
            $line->setLocationTo($this->getReference('location_'.$locationTo, Location::class));
        }

        if (null !== $locationFrom) {
            $line->setLocationFrom($this->getReference('location_'.$locationFrom, Location::class));
        }

        return $line;
    }

    private function manager(): User
    {
        return $this->getReference(UserFixtures::REFERENCE_MANAGER, User::class);
    }

    private function employee(): User
    {
        return $this->getReference(UserFixtures::REFERENCE_EMPLOYEE, User::class);
    }
}
