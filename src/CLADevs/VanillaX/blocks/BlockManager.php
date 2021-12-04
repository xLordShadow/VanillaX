<?php

namespace CLADevs\VanillaX\blocks;

use CLADevs\VanillaX\blocks\block\Beehive;
use CLADevs\VanillaX\blocks\block\BeeNest;
use CLADevs\VanillaX\blocks\block\campfire\Campfire;
use CLADevs\VanillaX\blocks\block\FlowerPotBlock;
use CLADevs\VanillaX\blocks\tile\campfire\RegularCampfireTile;
use CLADevs\VanillaX\blocks\tile\campfire\SoulCampfireTile;
use CLADevs\VanillaX\blocks\tile\FlowerPotTile;
use CLADevs\VanillaX\blocks\utils\BlockVanilla;
use CLADevs\VanillaX\blocks\utils\TileVanilla;
use CLADevs\VanillaX\items\ItemIdentifiers;
use CLADevs\VanillaX\items\ItemManager;
use CLADevs\VanillaX\utils\item\NonAutomaticCallItemTrait;
use CLADevs\VanillaX\utils\Utils;
use CLADevs\VanillaX\VanillaX;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockIdentifierFlattened;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockToolType;
use pocketmine\block\Door;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\FloorSign;
use pocketmine\block\Opaque;
use pocketmine\block\Planks;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\Transparent;
use pocketmine\block\Trapdoor;
use pocketmine\block\WallSign;
use pocketmine\block\tile\Sign as TileSign;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\item\ToolTier;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use const pocketmine\RESOURCE_PATH;

class BlockManager{
  use SingletonTrait;

  public function __construct(){
    self::setInstance($this);
  }

  /**
   * @throws ReflectionException
   */
  public function startup(): void{
    $this->initializeRuntimeIds();
    $this->initializeBlocks();
    $this->initializeTiles();

    Server::getInstance()->getAsyncPool()->addWorkerStartHook(function(int $worker): void{
      Server::getInstance()->getAsyncPool()->submitTaskToWorker(new class() extends AsyncTask{

        public function onRun(): void{
          BlockManager::getInstance()->initializeRuntimeIds();
        }
      }, $worker);
    });
  }

  public function initializeRuntimeIds(): void{
    $instance = RuntimeBlockMapping::getInstance();
    $method = new ReflectionMethod(RuntimeBlockMapping::class, "registerMapping");
    $method->setAccessible(true);

    $blockIdMap = json_decode(file_get_contents(RESOURCE_PATH . 'vanilla/block_id_map.json'), true);
    $metaMap = [];

    foreach($instance->getBedrockKnownStates() as $runtimeId => $nbt){
      $mcpeName = $nbt->getString("name");
      $meta = isset($metaMap[$mcpeName]) ? ($metaMap[$mcpeName] + 1) : 0;
      $id = $blockIdMap[$mcpeName] ?? BlockLegacyIds::AIR;

      if($id !== BlockLegacyIds::AIR && $meta <= 15 && !BlockFactory::getInstance()->isRegistered($id, $meta)){
        //var_dump("Runtime: $runtimeId Id: $id Name: $mcpeName Meta $meta");
        $metaMap[$mcpeName] = $meta;
        $method->invoke($instance, $runtimeId, $id, $meta);
      }
    }
  }

  /**
   * @throws ReflectionException
   */
  private function initializeTiles(): void{
    $tileConst = [];
    foreach((new ReflectionClass(TileVanilla::class))->getConstants() as $id => $value){
      $tileConst[$value] = $id;
    }

    Utils::callDirectory("blocks" . DIRECTORY_SEPARATOR . "tile", function (string $namespace)use($tileConst): void{
      $rc = new ReflectionClass($namespace);

      if(!$rc->isAbstract()){
        if($rc->implementsInterface(NonAutomaticCallItemTrait::class)){
          $diff = array_diff($rc->getInterfaceNames(), class_implements($rc->getParentClass()->getName()));

          if(in_array(NonAutomaticCallItemTrait::class, $diff)){
            return;
          }
        }
        $tileID = $rc->getConstant("TILE_ID");
        $tileBlock = $rc->getConstant("TILE_BLOCK");

        if($tileID !== false){
          $saveNames = [$tileID];
          $constID = $tileConst[$tileID] ?? null;

          if($constID !== null){
            $saveNames[] = "minecraft:" . strtolower($constID);
          }
          self::registerTile($namespace, $saveNames, $tileBlock === false ? BlockLegacyIds::AIR : $tileBlock);
        }else{
          VanillaX::getInstance()->getLogger()->error("Tile ID could not be found for '$namespace'");
        }
      }
    });
  }

  /**
   * @throws ReflectionException
   */
  private function initializeBlocks(): void{
    Utils::callDirectory("blocks" . DIRECTORY_SEPARATOR . "block", function (string $namespace): void{
      $rc = new ReflectionClass($namespace);

      if(!$rc->isAbstract()){
        if($rc->implementsInterface(NonAutomaticCallItemTrait::class)){
          $diff = array_diff($rc->getInterfaceNames(), class_implements($rc->getParentClass()->getName()));

          if(in_array(NonAutomaticCallItemTrait::class, $diff)){
            return;
          }
        }
        if(self::registerBlock(($class = new $namespace()), true, !$rc->implementsInterface(NonAutomaticCallItemTrait::class)) && $class instanceof Block && $class->ticksRandomly()){
          foreach(Server::getInstance()->getWorldManager()->getWorlds() as $world){
            $world->addRandomTickedBlock($class);
          }
        }
      }
    });
    $this->registerFlowerPot();
    $this->registerCampfire();
    $this->registerNylium();
    $this->registerRoots();
    $this->registerChiseled();
    $this->registerCracked();
    $this->registerPlanks();
    $this->registerDoors();
    $this->registerFence();
    $this->registerStairs();
    //$this->registerSigns();
    $this->registerTrapdoors();
    $this->registerSlabs();

    self::registerBlock(new Block(new BlockIdentifier(BlockLegacyIds::SLIME_BLOCK, 0), "Slime", new BlockBreakInfo(0)));
    self::registerBlock(new Block(new BlockIdentifier(BlockVanilla::ANCIENT_DEBRIS, 0, ItemIdentifiers::ANCIENT_DEBRIS), "Ancient Debris", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 6000.0)));

    self::registerBlock(new Beehive());
    self::registerBlock(new BeeNest());

    self::registerBlock(new Transparent(new BlockIdentifier(BlockVanilla::HONEY_BLOCK, 0, ItemIdentifiers::HONEY_BLOCK), "Honey Block", new BlockBreakInfo(0)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::HONEYCOMB_BLOCK, 0, ItemIdentifiers::HONEYCOMB_BLOCK), "Honeycomb Block", new BlockBreakInfo(0.6, BlockToolType::NONE, 0, 0.6)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::LODESTONE, 0, ItemIdentifiers::LODESTONE), "Lodestone", new BlockBreakInfo(3.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 3.5)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::TARGET, 0, ItemIdentifiers::TARGET), "Target", new BlockBreakInfo(0.5, BlockToolType::HOE, 0, 0.5)));

    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::BLACKSTONE, 0, ItemIdentifiers::BLACKSTONE), "Blackstone", new BlockBreakInfo(1.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 6.0)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::POLISHED_BLACKSTONE_BRICKS, 0, ItemIdentifiers::POLISHED_BLACKSTONE_BRICKS), "Polished Blackstone Bricks", new BlockBreakInfo(1.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 6.0)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::GILDED_BLACKSTONE, 0, ItemIdentifiers::GILDED_BLACKSTONE), "Gilded Blackstone", new BlockBreakInfo(1.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 6.0)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::NETHER_GOLD_ORE, 0, ItemIdentifiers::NETHER_GOLD_ORE), "Nether Gold Ore", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 3.0)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::CRYING_OBSIDIAN, 0, ItemIdentifiers::CRYING_OBSIDIAN), "Crying Obsidian", new BlockBreakInfo(50, BlockToolType::PICKAXE, ToolTier::DIAMOND()->getHarvestLevel(), 1200.0)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::POLISHED_BLACKSTONE, 0, ItemIdentifiers::POLISHED_BLACKSTONE), "Polished Blackstone", new BlockBreakInfo(2.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 6.0)));
  }

  private function registerFlowerPot(): void{
    $flowerPot = new FlowerPotBlock(new BlockIdentifier(BlockLegacyIds::FLOWER_POT_BLOCK, 0, ItemIds::FLOWER_POT, FlowerPotTile::class), "Flower Pot", BlockBreakInfo::instant());
    self::registerBlock($flowerPot);
    for($meta = 1; $meta < 16; ++$meta){
      BlockFactory::getInstance()->remap(BlockLegacyIds::FLOWER_POT_BLOCK, $meta, $flowerPot);
    }
  }

  private function registerCampfire(): void{
    self::registerBlock(new Campfire(new BlockIdentifier(BlockLegacyIds::CAMPFIRE, 0, ItemIdentifiers::CAMPFIRE, RegularCampfireTile::class), "Campfire", new BlockBreakInfo(2, BlockToolType::AXE, 0, 2)));
    self::registerBlock(new Campfire(new BlockIdentifier(BlockVanilla::SOUL_CAMPFIRE, 0, ItemIdentifiers::SOUL_CAMPFIRE, SoulCampfireTile::class), "Soul Campfire", new BlockBreakInfo(2, BlockToolType::AXE, 0, 2)));
  }

  private function registerNylium(): void{
    self::registerBlock(new Block(new BlockIdentifier(BlockVanilla::CRIMSON_NYLIUM, 0, ItemIdentifiers::CRIMSON_NYLIUM), "Crimson Nylium", new BlockBreakInfo(0.4, BlockToolType::PICKAXE, 0, 1)));
    self::registerBlock(new Block(new BlockIdentifier(BlockVanilla::WARPED_NYLIUM, 0, ItemIdentifiers::WARPED_NYLIUM), "Warped Nylium", new BlockBreakInfo(0.4, BlockToolType::PICKAXE, 0, 1)));
  }

  private function registerRoots(): void{
    self::registerBlock(new Transparent(new BlockIdentifier(BlockVanilla::CRIMSON_ROOTS, 0, ItemIdentifiers::CRIMSON_ROOTS), "Crimson Roots", BlockBreakInfo::instant()));
    self::registerBlock(new Transparent(new BlockIdentifier(BlockVanilla::WARPED_ROOTS, 0, ItemIdentifiers::WARPED_ROOTS), "Warped Roots", BlockBreakInfo::instant()));
  }

  private function registerChiseled(): void{
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::CHISELED_NETHER_BRICKS, 0, ItemIdentifiers::CHISELED_NETHER_BRICKS), "Chiseled Nether Bricks", new BlockBreakInfo(2, BlockToolType::PICKAXE, 0, 6)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::CHISELED_POLISHED_BLACKSTONE, 0, ItemIdentifiers::CHISELED_POLISHED_BLACKSTONE), "Chiseled Polished Blackstone", new BlockBreakInfo(1.5, BlockToolType::PICKAXE, 0, 6)));
  }

  private function registerCracked(): void{
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::CRACKED_NETHER_BRICKS, 0, ItemIdentifiers::CRACKED_NETHER_BRICKS), "Cracked Nether Bricks", new BlockBreakInfo(2, BlockToolType::PICKAXE, 0, 6)));
    self::registerBlock(new Opaque(new BlockIdentifier(BlockVanilla::CRACKED_POLISHED_BLACKSTONE_BRICKS, 0, ItemIdentifiers::CRACKED_POLISHED_BLACKSTONE_BRICKS), "Cracked Polished Blackstone Bricks", new BlockBreakInfo(1.5, BlockToolType::PICKAXE, 0, 6)));
  }

  private function registerPlanks(): void{
    self::registerBlock(new Planks(new BlockIdentifier(BlockVanilla::CRIMSON_PLANKS, 0, ItemIdentifiers::CRIMSON_PLANKS), "Crimson Planks", new BlockBreakInfo(2, BlockToolType::AXE, 0, 3)));
    self::registerBlock(new Planks(new BlockIdentifier(BlockVanilla::WARPED_PLANKS, 0, ItemIdentifiers::WARPED_PLANKS), "Warped Planks", new BlockBreakInfo(2, BlockToolType::AXE, 0, 3)));
  }

  private function registerDoors(): void{
    self::registerBlock(new Door(new BlockIdentifier(BlockVanilla::CRIMSON_DOOR, 0, ItemIdentifiers::CRIMSON_DOOR), "Crimson Door", new BlockBreakInfo(3, BlockToolType::AXE)));
    self::registerBlock(new Door(new BlockIdentifier(BlockVanilla::WARPED_DOOR, 0, ItemIdentifiers::WARPED_DOOR), "Warped Door", new BlockBreakInfo(3, BlockToolType::AXE)));
  }

  private function registerFence(): void{
    //fences
    self::registerBlock(new Fence(new BlockIdentifier(BlockVanilla::CRIMSON_FENCE, 0, ItemIdentifiers::CRIMSON_FENCE), "Crimson Fence", new BlockBreakInfo(2, BlockToolType::AXE, 0, 3)));
    self::registerBlock(new Fence(new BlockIdentifier(BlockVanilla::WARPED_FENCE, 0, ItemIdentifiers::WARPED_FENCE), "Warped Fence", new BlockBreakInfo(2, BlockToolType::AXE, 0, 3)));
    //gates
    self::registerBlock(new FenceGate(new BlockIdentifier(BlockVanilla::CRIMSON_FENCE_GATE, 0, ItemIdentifiers::CRIMSON_FENCE_GATE), "Crimson Fence Gate", new BlockBreakInfo(2, BlockToolType::AXE, 0, 3)));
    self::registerBlock(new FenceGate(new BlockIdentifier(BlockVanilla::WARPED_FENCE_GATE, 0, ItemIdentifiers::WARPED_FENCE_GATE), "Warped Fence Gate", new BlockBreakInfo(2, BlockToolType::AXE, 0, 3)));
  }

  private function registerStairs(): void{
    self::registerBlock(new Stair(new BlockIdentifier(BlockVanilla::BLACKSTONE_STAIRS, 0, ItemIdentifiers::BLACKSTONE_STAIRS), "Blackstone Stairs", new BlockBreakInfo(3, BlockToolType::AXE, 0, 6)));
    self::registerBlock(new Stair(new BlockIdentifier(BlockVanilla::CRIMSON_STAIRS, 0, ItemIdentifiers::CRIMSON_STAIRS), "Crimson Stairs", new BlockBreakInfo(3, BlockToolType::AXE, 0, 6)));
    self::registerBlock(new Stair(new BlockIdentifier(BlockVanilla::POLISHED_BLACKSTONE_BRICK_STAIRS, 0, ItemIdentifiers::POLISHED_BLACKSTONE_BRICK_STAIRS), "Polished Blackstone Brick Stairs", new BlockBreakInfo(3, BlockToolType::AXE, 0, 6)));
    self::registerBlock(new Stair(new BlockIdentifier(BlockVanilla::POLISHED_BLACKSTONE_STAIRS, 0, ItemIdentifiers::POLISHED_BLACKSTONE_STAIRS), "Polished Blackstone Stairs", new BlockBreakInfo(3, BlockToolType::AXE, 0, 6)));
    self::registerBlock(new Stair(new BlockIdentifier(BlockVanilla::WARPED_STAIRS, 0, ItemIdentifiers::WARPED_STAIRS), "Warped Stairs", new BlockBreakInfo(3, BlockToolType::AXE, 0, 6)));
  }

  private function registerSigns() : void{
    self::registerBlock(new FloorSign(new BlockIdentifier(BlockVanilla::CRIMSON_STANDING_SIGN, 0, ItemIdentifiers::CRIMSON_STANDING_SIGN, TileSign::class), "Crimson Sign", new BlockBreakInfo(1.0, BlockToolType::AXE, 0, 1.0)));
    self::registerBlock(new FloorSign(new BlockIdentifier(BlockVanilla::WARPED_STANDING_SIGN, 0, ItemIdentifiers::WARPED_STANDING_SIGN, TileSign::class), "Warped Sign", new BlockBreakInfo(1.0, BlockToolType::AXE, 0, 1.0)));

    self::registerBlock(new WallSign(new BlockIdentifier(BlockVanilla::CRIMSON_WALL_SIGN, 0, ItemIdentifiers::CRIMSON_WALL_SIGN, TileSign::class), "Crimson Wall Sign", new BlockBreakInfo(1.0, BlockToolType::AXE, 0, 1.0)));
    self::registerBlock(new WallSign(new BlockIdentifier(BlockVanilla::WARPED_WALL_SIGN, 0, ItemIdentifiers::WARPED_WALL_SIGN, TileSign::class), "Warped Wall Sign", new BlockBreakInfo(1.0, BlockToolType::AXE, 0, 1.0)));
  }

  private function registerTrapdoors() : void{
    self::registerBlock(new Trapdoor(new BlockIdentifier(BlockVanilla::CRIMSON_TRAPDOOR, 0, ItemIdentifiers::CRIMSON_TRAPDOOR), "Crimson Trapdoor", new BlockBreakInfo(3, BlockToolType::AXE, 0, 3.0)));
    self::registerBlock(new Trapdoor(new BlockIdentifier(BlockVanilla::WARPED_TRAPDOOR, 0, ItemIdentifiers::WARPED_TRAPDOOR), "Warped Trapdoor", new BlockBreakInfo(3, BlockToolType::AXE, 0, 3.0)));
  }

  private function registerSlabs() : void{
    self::registerBlock(new Slab(new BlockIdentifierFlattened(BlockVanilla::CRIMSON_SLAB, [BlockVanilla::CRIMSON_DOUBLE_SLAB], 0), "Crimson Slab", new BlockBreakInfo(2.0, BlockToolType::AXE, 0, 15.0)));
    self::registerBlock(new Slab(new BlockIdentifierFlattened(BlockVanilla::WARPED_SLAB, [BlockVanilla::WARPED_DOUBLE_SLAB], 0), "Warped Slab", new BlockBreakInfo(2.0, BlockToolType::AXE, 0, 15.0)));
  }

  public function registerBlock(Block $block, bool $override = true, bool $creativeItem = false): bool{
    if(in_array($block->getId(), VanillaX::getInstance()->getConfig()->getNested("disabled.blocks", []))){
      return false;
    }
    BlockFactory::getInstance()->register($block, $override);
    $item = $block->asItem();
    $itemBlock = $item->getBlock();

    if($itemBlock->getId() !== $block->getId() || $itemBlock->getMeta() !== $block->getMeta()){
      ItemManager::register(new class(new ItemIdentifier($item->getId(), $item->getMeta()), $block->getName(), $block) extends Item{

        private Block $block;
        public function __construct(ItemIdentifier $identifier, string $name, Block $block){
          parent::__construct($identifier, $name);
          $this->block = $block;
        }

        public function getBlock(?int $clickedFace = null): Block{
          return $this->block;
        }
      }, $creativeItem, true);
    }elseif(!CreativeInventory::getInstance()->contains($item)){
      CreativeInventory::getInstance()->add($item);
    }
    return true;
  }

  /**
   * @param string $namespace Class of the Tile
   * @param array $names Save names for Tile, for such use as Tile::createTile
   * @param array|int $blockId Block the Tile was made for not necessary
   * @return bool returns true if it succeed, if not it returns false
   */
  public function registerTile(string $namespace, array $names = [], $blockId = BlockLegacyIds::AIR): bool{
    if(!is_array($blockId)){
      $blockId = [$blockId];
    }
    foreach($blockId as $id){
      if($id !== BlockLegacyIds::AIR && in_array($id, VanillaX::getInstance()->getConfig()->getNested("disabled.blocks", []))){
        return false;
      }
    }
    TileFactory::getInstance()->register($namespace, $names);
    return true;
  }
}