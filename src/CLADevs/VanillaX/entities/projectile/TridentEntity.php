<?php

namespace CLADevs\VanillaX\entities\projectile;

use pocketmine\entity\Entity;

class TridentEntity extends Entity{

    public $width = 0.25;
    public $height = 0.35;

    const NETWORK_ID = self::THROWN_TRIDENT;
}