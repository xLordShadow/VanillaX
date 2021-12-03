<?php

namespace CLADevs\VanillaX\session;

use CLADevs\VanillaX\entities\passive\VillagerEntity;
use CLADevs\VanillaX\entities\projectile\TridentEntity;
use CLADevs\VanillaX\entities\utils\interfaces\EntityRidable;
use CLADevs\VanillaX\entities\VanillaEntity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

class Session{

    /** @var TridentEntity[] */
    private array $thrownTridents = [];

    private string $interactiveText = "";

    private int $entityId;

    private bool $gliding = false;
    private bool $swimming = false;

    private Player $player;

    public function __construct(Player $player){
        $this->player = $player;
        $this->entityId = $player->getId();
    }

    public function getEntityId(): int{
        return $this->entityId;
    }

    public function getInteractiveText(): string{
        return $this->interactiveText;
    }

    public function setInteractiveText(string $interactiveText): void{
        $this->interactiveText = $interactiveText;
        $this->player->getNetworkProperties()->setString(EntityMetadataProperties::INTERACTIVE_TAG, $interactiveText);
    }

    public function getPlayer(): Player{
        return $this->player;
    }

    public function isGliding(): bool{
        return $this->gliding;
    }

    public function setGliding(bool $gliding): void{
        $this->player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::GLIDING, $gliding);
        $this->gliding = $gliding;
    }

    public function isSwimming(): bool{
        return $this->swimming;
    }

    public function setSwimming(bool $value): void{
        $this->player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SWIMMING, $value);
        $this->swimming = $value;
    }

    /**
     * @return TridentEntity[]
     */
    public function getThrownTridents(): array{
        return $this->thrownTridents;
    }

    public function addTrident(TridentEntity $entity): void{
        $this->thrownTridents[$entity->getId()] = $entity;
    }

    public function removeTrident(TridentEntity $entity): void{
        if(isset($this->thrownTridents[$entity->getId()])) unset($this->thrownTridents[$entity->getId()]);
    }

    /**
     * @param Player|Vector3 $player
     * @param string $sound
     * @param float $pitch
     * @param float $volume
     * @param bool $packet
     * @return PlaySoundPacket|null
     */
    public static function playSound(Player|Vector3 $player, string $sound, float $pitch = 1, float $volume = 1, bool $packet = false): ?DataPacket{
        $pos = $player instanceof Player ? $player->getPosition() : $player;
        $pk = new PlaySoundPacket();
        $pk->soundName = $sound;
        $pk->x = $pos->x;
        $pk->y = $pos->y;
        $pk->z = $pos->z;
        $pk->pitch = $pitch;
        $pk->volume = $volume;
        if($packet){
            return $pk;
        }elseif($player instanceof Player){
            $player->getNetworkSession()->sendDataPacket($pk);
        }
        return null;
    }
}