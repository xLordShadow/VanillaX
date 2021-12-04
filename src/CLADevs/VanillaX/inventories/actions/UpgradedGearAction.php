<?php

namespace CLADevs\VanillaX\inventories\actions;

use CLADevs\VanillaX\inventories\types\SmithingTableInventory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Item;
use pocketmine\player\Player;

class UpgradedGearAction extends InventoryAction{

    private int $sourceType;

    public function __construct(Item $sourceItem, Item $targetItem, int $sourceType){
        parent::__construct($sourceItem, $targetItem);
        $this->sourceType = $sourceType;
    }

    public function execute(Player $source): void{
        $inv = $source->getCurrentWindow();

        if($inv instanceof SmithingTableInventory && $this->targetItem->getId() === BlockLegacyIds::AIR){
            $inv->onSuccess($source, $this->sourceItem);
        }
    }

    public function validate(Player $source): void{
        if(!$source->getCurrentWindow() instanceof SmithingTableInventory){
            throw new TransactionValidationException("Smithing Table Inventory is not opened");
        }
    }

    public function getType(): int{
        return $this->sourceType;
    }
}