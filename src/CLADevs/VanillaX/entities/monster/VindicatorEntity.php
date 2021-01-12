<?php

namespace CLADevs\VanillaX\entities\monster;

use CLADevs\VanillaX\entities\LivingEntity;

class VindicatorEntity extends LivingEntity{

    public $width = 0.6;
    public $height = 1.9;

    const NETWORK_ID = self::VINDICATOR;

    private bool $spawnedNaturallyEquipped = false;

    protected function initEntity(): void{
        parent::initEntity();
        $this->setMaxHealth(24);
    }

    public function getName(): string{
        return "Vindicator";
    }

    public function isSpawnedNaturallyEquipped(): bool{
        return $this->spawnedNaturallyEquipped;
    }
}