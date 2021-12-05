<?php


namespace CLADevs\VanillaX\blocks\block;

use CLADevs\VanillaX\blocks\utils\BlockVanilla;
use CLADevs\VanillaX\items\ItemIdentifiers;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\Opaque;
use pocketmine\block\utils\BlockDataSerializer;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Beehive extends Opaque {
  use FacesOppositePlacingPlayerTrait;
  use HorizontalFacingTrait;

  public function __construct(){
    parent::__construct(new BlockIdentifier(BlockVanilla::BEEHIVE, 0, ItemIdentifiers::BEEHIVE), "Beehive", new BlockBreakInfo(0.6, BlockToolType::AXE, 0, 0.6));
  }

  public function readStateFromData(int $id, int $stateMeta) : void{
    $this->facing = BlockDataSerializer::readLegacyHorizontalFacing($stateMeta & 0x03);
  }

  protected function writeStateToMeta() : int{
    return BlockDataSerializer::writeLegacyHorizontalFacing($this->facing);
  }

  public function getStateBitmask() : int{
    return 0b11;
  }
}