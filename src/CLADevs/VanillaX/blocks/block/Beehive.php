<?php


namespace CLADevs\VanillaX\blocks\block;

use CLADevs\VanillaX\blocks\utils\BlockVanilla;
use CLADevs\VanillaX\items\ItemIdentifiers;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\Opaque;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Beehive extends Opaque {

  private int $facing = 0;

  public function __construct(){
    parent::__construct(new BlockIdentifier(BlockVanilla::BEEHIVE, 0, ItemIdentifiers::BEEHIVE), "Beehive", new BlockBreakInfo(0.6, BlockToolType::AXE, 0, 0.6));
  }

  public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool {
    $faces = [1 => 0, 2 => 1, 3 => 2, 4 => 3];
    $this->facing = $faces[$face] ?? $face;
    return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
  }

  protected function writeStateToMeta(): int {
    return $this->facing;
  }
}