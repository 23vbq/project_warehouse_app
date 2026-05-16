<?php

namespace App\DataFixtures;

use App\Entity\Location;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LocationFixtures extends Fixture implements DependentFixtureInterface
{
    private const ZONES = [
        'A' => 'Strefa A — surowce i materiały',
        'B' => 'Strefa B — półprodukty',
        'C' => 'Strefa C — wyroby gotowe',
    ];

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $createdBy = $this->getReference(UserFixtures::REFERENCE_MANAGER, User::class);

        foreach (self::ZONES as $zone => $zoneName) {
            for ($i = 1; $i <= 10; ++$i) {
                $code = sprintf('%s-%02d', $zone, $i);

                $location = (new Location())
                    ->setCode($code)
                    ->setName(sprintf('%s / regał %02d', $zoneName, $i))
                    ->setCreatedBy($createdBy);

                $manager->persist($location);
                $this->addReference('location_'.$code, $location);
            }
        }

        $manager->flush();
    }
}
