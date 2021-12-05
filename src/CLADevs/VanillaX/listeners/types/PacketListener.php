<?php

namespace CLADevs\VanillaX\listeners\types;

use CLADevs\VanillaX\entities\utils\EntityInteractResult;
use CLADevs\VanillaX\entities\utils\interfaces\EntityInteractable;
use CLADevs\VanillaX\entities\utils\interfaces\EntityRidable;
use CLADevs\VanillaX\inventories\FakeBlockInventory;
use CLADevs\VanillaX\inventories\InventoryManager;
use CLADevs\VanillaX\listeners\ListenerManager;
use CLADevs\VanillaX\session\Session;
use CLADevs\VanillaX\utils\instances\InteractButtonResult;
use CLADevs\VanillaX\utils\item\InteractButtonItemTrait;
use CLADevs\VanillaX\VanillaX;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\java\GameModeIdMap;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBlockPickEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ActorPickRequestPacket;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\CommandBlockUpdatePacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\SetDefaultGameTypePacket;
use pocketmine\network\mcpe\protocol\SetDifficultyPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\recipe\PotionContainerChangeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\PotionTypeRecipe;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use const pocketmine\BEDROCK_DATA_PATH;

class PacketListener implements Listener{

    private ListenerManager $manager;

    public function __construct(ListenerManager $manager){
        $this->manager = $manager;
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void{
        VanillaX::getInstance()->getEnchantmentManager()->handleInventoryTransaction($event);
    }

    public function onDataPacketSend(DataPacketSendEvent $event): void{
        if(!$event->isCancelled()){
            foreach($event->getPackets() as $packet){
                switch($packet::NETWORK_ID){
                    case ProtocolInfo::CRAFTING_DATA_PACKET:
                        if($packet instanceof CraftingDataPacket) $this->handleCraftingData($packet);
                        break;
                }
            }
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void{
        if(!$event->isCancelled() && ($player = $event->getOrigin()->getPlayer()) !== null){
            $packet = $event->getPacket();
            $sessionManager = VanillaX::getInstance()->getSessionManager();
            $session = $sessionManager->has($player) ? $sessionManager->get($player) : null;
            $window = $player->getCurrentWindow();

            if($window instanceof FakeBlockInventory && !$window->handlePacket($player, $packet)){
                $event->cancel();
                return;
            }
            switch($packet::NETWORK_ID){
                case ProtocolInfo::COMMAND_BLOCK_UPDATE_PACKET:
                    if($packet instanceof CommandBlockUpdatePacket && $player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) $this->handleCommandBlock($player, $packet);
                    break;
                case ProtocolInfo::PLAYER_ACTION_PACKET:
                    if($packet instanceof PlayerActionPacket) $this->handlePlayerAction($session, $packet);
                    break;
                case ProtocolInfo::INVENTORY_TRANSACTION_PACKET:
                    if($packet instanceof InventoryTransactionPacket) $this->handleInventoryTransaction($player, $packet);
                    break;
                case ProtocolInfo::SET_PLAYER_GAME_TYPE_PACKET:
                    /** Server Form Personal Game Type Setting */
                    if($player->hasPermission(DefaultPermissions::ROOT_OPERATOR) && $packet instanceof SetPlayerGameTypePacket){
                        $player->setGamemode(GameModeIdMap::getInstance()->fromId($packet->gamemode));
                    }
                    break;
                case ProtocolInfo::SET_DEFAULT_GAME_TYPE_PACKET:
                    /** Server Form Default Game Type Setting */
                    if($player->hasPermission(DefaultPermissions::ROOT_OPERATOR) && $packet instanceof SetDefaultGameTypePacket){
                        Server::getInstance()->getConfigGroup()->setConfigInt("gamemode", $packet->gamemode);
                    }
                    break;
                case ProtocolInfo::SET_DIFFICULTY_PACKET:
                    /** Server Form Difficulty Setting */
                    if($player->hasPermission(DefaultPermissions::ROOT_OPERATOR) && $packet instanceof SetDifficultyPacket){
                        $player->getWorld()->setDifficulty($packet->difficulty);
                    }
                    break;
            }
        }
    }

    /**
     * @param Player $player
     * @param InventoryTransactionPacket $packet
     * This is for interacting with villagers for trading, changing armor stand armor, etc.
     */
    private function handleInventoryTransaction(Player $player, InventoryTransactionPacket $packet): void{
        if($packet->trData instanceof UseItemOnEntityTransactionData && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_INTERACT){
            $entity = $player->getWorld()->getEntity($packet->trData->getActorRuntimeId());
            $item = TypeConverter::getInstance()->netItemStackToCore($packet->trData->getItemInHand()->getItemStack());
            $currentButton = VanillaX::getInstance()->getSessionManager()->get($player)->getInteractiveText();
            $clickPos = $packet->trData->getClickPosition();
            $button = null;

            if(is_string($currentButton) && count($packet->trData->getActions()) < 1){
                if($entity instanceof InteractButtonItemTrait){
                    /** Whenever a player interacts with interactable button for entity */
                    $entity->onButtonPressed($button = new InteractButtonResult($player, $item, $currentButton, $clickPos));
                }
                if($item instanceof InteractButtonItemTrait){
                    /** Whenever a player interacts with interactable button for item */
                    $item->onButtonPressed($button = new InteractButtonResult($player, $item, $currentButton, $clickPos));
                }
            }

            if($entity instanceof EntityInteractable){
                /** If a player interacts with entity with a item */
                if($button === null || $button->canInteractQueue()){
                    $entity->onInteract(new EntityInteractResult($player, $item, null, $clickPos, $currentButton));
                }
            }
            if($item instanceof EntityInteractable){
                /** If a player interacts with entity with a item that has EntityInteractable traits */
                $item->onInteract(new EntityInteractResult($player, null, $entity));
            }
        }
    }

    /**
     * @param CraftingDataPacket $packet
     * called whenever player joins to send recipes for brewing, crafting, etc
     */
    private function handleCraftingData(CraftingDataPacket $packet): void{
        $manager = InventoryManager::getInstance();
        $translator = ItemTranslator::getInstance();
        $recipes = json_decode(file_get_contents(BEDROCK_DATA_PATH . "recipes.json"), true);

        $potionTypeRecipes = [];
        foreach($recipes["potion_type"] as $recipe){
            [$inputNetId, $inputNetDamage] = $translator->toNetworkId($recipe["input"]["id"], $recipe["input"]["damage"] ?? 0);
            [$ingredientNetId, $ingredientNetDamage] = $translator->toNetworkId($recipe["ingredient"]["id"], $recipe["ingredient"]["damage"] ?? 0);
            [$outputNetId, $outputNetDamage] = $translator->toNetworkId($recipe["output"]["id"], $recipe["output"]["damage"] ?? 0);
            $potion = new PotionTypeRecipe($inputNetId, $inputNetDamage, $ingredientNetId, $ingredientNetDamage, $outputNetId, $outputNetDamage);
            $packet->potionTypeRecipes[] = $potion;
            $potion = $manager->internalPotionTypeRecipe(clone $potion);
            $potionTypeRecipes[$manager->hashPotionType($potion)] = $potion;
        }

        $potionContainerRecipes = [];
        foreach($recipes["potion_container_change"] as $recipe){
            $inputNetId = $translator->toNetworkId($recipe["input_item_id"], 0)[0];
            $ingredientNetId = $translator->toNetworkId($recipe["ingredient"]["id"], 0)[0];
            $outputNetId = $translator->toNetworkId($recipe["output_item_id"], 0)[0];
            $potion = new PotionContainerChangeRecipe($inputNetId, $ingredientNetId, $outputNetId);
            $packet->potionContainerRecipes[] = $potion;
            $potion = $manager->internalPotionContainerRecipe(clone $potion);
            $potionContainerRecipes[$manager->hashPotionContainer($potion)] = $potion;
        }

        InventoryManager::getInstance()->setPotionTypeRecipes($potionTypeRecipes);
        InventoryManager::getInstance()->setPotionContainerRecipes($potionContainerRecipes);
    }

    /**
     * @param Session $session
     * @param PlayerActionPacket $packet
     * this packet is sent by player whenever they want to swim, jump, break, use elytra, etc
     */
    private function handlePlayerAction(Session $session, PlayerActionPacket $packet): void{
        if($packet instanceof PlayerActionPacket && in_array($packet->action, [PlayerAction::START_GLIDE, PlayerAction::STOP_GLIDE])){
            $session->setGliding($packet->action === PlayerAction::START_GLIDE);
        }
        if($packet instanceof PlayerActionPacket && in_array($packet->action, [PlayerAction::START_SWIMMING, PlayerAction::STOP_SWIMMING])){
            $session->setSwimming($packet->action === PlayerAction::START_SWIMMING);
        }
    }
}