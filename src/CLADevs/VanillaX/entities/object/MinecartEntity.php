<?php

namespace CLADevs\VanillaX\entities\object;

use pocketmine\entity\Entity;

class MinecartEntity extends Entity{

    public $width = 0.98;
    public $height = 0.7;

    const NETWORK_ID = self::MINECART;
}