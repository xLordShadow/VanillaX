<?php

namespace CLADevs\VanillaX\entities\projectile;

use pocketmine\entity\Entity;

class FireballEntity extends Entity{

    public $width = 0.31;
    public $height = 0.31;

    const NETWORK_ID = self::FIREBALL;
}