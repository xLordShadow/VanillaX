<?php

namespace CLADevs\VanillaX\network\protocol;

use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\PacketHandlerInterface;
use pocketmine\network\mcpe\protocol\types\ActorEvent;

class ActorEventPacketX extends ActorEventPacket{

    public function handle(PacketHandlerInterface $handler): bool{
        $parent = parent::handle($handler);

        if($this->event === ActorEvent::PLAYER_ADD_XP_LEVELS || $this->event === ActorEvent::COMPLETE_TRADE){
            return true; //ignores debug
        }
        return $parent;
    }
}