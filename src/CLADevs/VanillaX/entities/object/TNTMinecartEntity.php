<?php

namespace CLADevs\VanillaX\entities\object;

use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class TNTMinecartEntity extends MinecartEntity{

    const NETWORK_ID = EntityIds::TNT_MINECART;

    public function kill(): void{
        parent::kill();
    }
}