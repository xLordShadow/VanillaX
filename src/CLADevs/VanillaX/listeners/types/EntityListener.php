<?php

namespace CLADevs\VanillaX\listeners\types;

use CLADevs\VanillaX\blocks\block\TwistingVinesBlock;
use CLADevs\VanillaX\items\ItemIdentifiers;
use CLADevs\VanillaX\items\types\ShieldItem;
use CLADevs\VanillaX\VanillaX;
use CLADevs\VanillaX\world\sounds\ShieldBlockSound;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\player\Player;

class EntityListener implements Listener{

    public function onDamage(EntityDamageEvent $event): void{
        if(!$event->isCancelled()){
            $entity = $event->getEntity();

            VanillaX::getInstance()->getEnchantmentManager()->handleDamage($event); /** Enchantment Damage */
            switch($event->getCause()){
                case EntityDamageEvent::CAUSE_FALL:
                    if($this->handleFallDamage($entity)) $event->cancel();
                    break;
                case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                    if($event instanceof EntityDamageByEntityEvent && $entity instanceof Player){
                        $item = $entity->getInventory()->getItemInHand();
                        if($entity->isSneaking() && $this->handleShieldDamage($event->getDamager(), $entity, $item)){
                            $event->cancel();
                        }
                    }
                    break;
            }
            if($event instanceof EntityDamageByEntityEvent && $entity instanceof Player){
                $resist = 1;

                foreach($entity->getArmorInventory()->getContents(true) as $item){
                    if(in_array($item->getId(), [ItemIdentifiers::NETHERITE_HELMET, ItemIdentifiers::NETHERITE_CHESTPLATE, ItemIdentifiers::NETHERITE_LEGGINGS, ItemIdentifiers::NETHERITE_BOOTS])){
                        $resist += 2;
                    }
                }
                if($resist < 0){
                    $resist = 1;
                }elseif($resist > 1){
                    $resist -= 1;
                }
                $event->setKnockBack($event->getKnockBack() / $resist);
            }
        }
    }

    private function handleShieldDamage(Entity $damager, Player $entity, Item $item): bool{
        if($damager instanceof Living){
            $offhand = $entity->getOffHandInventory();
            $minYaw = $entity->getLocation()->yaw - 90;
            $maxYaw = $entity->getLocation()->yaw + 90;
            $xDist = $damager->getPosition()->x - $entity->getPosition()->x;
            $zDist = $damager->getPosition()->z - $entity->getPosition()->z;
            $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;

            if($yaw < 0){
                $yaw += 360.0;
            }

            if(($offhandItem = $offhand->getItem(0)) instanceof ShieldItem){
                $item = $offhandItem;
            }elseif(!$item instanceof ShieldItem){
                return false;
            }
            if($yaw >= $minYaw && $yaw <= $maxYaw){
                $item->applyDamage(1);
                if($offhandItem instanceof ShieldItem){
                    $offhand->setItem(0, $item);
                }else{
                    $entity->getInventory()->setItemInHand($item);
                }
                $entity->getWorld()->addSound($entity->getPosition(), new ShieldBlockSound());
                $entity->knockBack($damager->getPosition()->x - $entity->getPosition()->x, $damager->getPosition()->z - $entity->getPosition()->z, 0.5);
                return true;
            }
        }
        return false;
    }

    private function handleFallDamage(Entity $entity): bool{
        $cancel = false;

        if($entity instanceof Player && $entity->getPosition()->getWorld()->getBlock($entity->getPosition()->subtract(0, 1, 0)) instanceof TwistingVinesBlock){
            $cancel = true;
        }
        return $cancel;
    }
}
