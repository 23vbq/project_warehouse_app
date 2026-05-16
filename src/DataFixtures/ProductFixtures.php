<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    private const PRODUCTS = [
        ['SKU-FIN-001', 'Felga aluminiowa 17" ET35',   ProductType::FINISHED,    'szt',  '450.00', '10.000'],
        ['SKU-FIN-002', 'Felga aluminiowa 18" ET40',   ProductType::FINISHED,    'szt',  '520.00',  '8.000'],
        ['SKU-FIN-003', 'Felga aluminiowa 16" ET30',   ProductType::FINISHED,    'szt',  '380.00', '12.000'],
        ['SKU-SEM-001', 'Półfabrykat felgi 17"',       ProductType::SEMI,        'szt',  '180.00',    null],
        ['SKU-SEM-002', 'Półfabrykat felgi 18"',       ProductType::SEMI,        'szt',  '210.00',    null],
        ['SKU-RAW-001', 'Wlewek aluminium 99.7%',      ProductType::RAW,         'kg',    '22.50', '500.000'],
        ['SKU-RAW-002', 'Pręt aluminium Ø60',          ProductType::RAW,         'mb',    '38.00', '100.000'],
        ['SKU-RAW-003', 'Drut spawalniczy ER4043',     ProductType::RAW,         'kg',    '45.00',  '50.000'],
        ['SKU-CON-001', 'Olej do obróbki CNC',         ProductType::CONSUMABLES, 'l',     '18.00',  '20.000'],
        ['SKU-CON-002', 'Tarcza szlifierska 200mm',    ProductType::CONSUMABLES, 'szt',   '12.00',  '30.000'],
        ['SKU-CON-003', 'Rękawice robocze',            ProductType::CONSUMABLES, 'para',   '8.50',  '50.000'],
        ['SKU-CON-004', 'Płyn chłodzący',              ProductType::CONSUMABLES, 'l',     '25.00',  '15.000'],
    ];

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $createdBy = $this->getReference(UserFixtures::REFERENCE_MANAGER, User::class);

        foreach (self::PRODUCTS as [$sku, $name, $type, $unit, $unitPrice, $minStockLevel]) {
            $product = (new Product())
                ->setSku($sku)
                ->setName($name)
                ->setType($type)
                ->setUnit($unit)
                ->setUnitPrice($unitPrice)
                ->setMinStockLevel($minStockLevel)
                ->setCreatedBy($createdBy);

            $manager->persist($product);
            $this->addReference('product_'.$sku, $product);
        }

        $manager->flush();
    }
}
