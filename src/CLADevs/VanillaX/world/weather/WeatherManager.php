<?php

namespace CLADevs\VanillaX\world\weather;

use CLADevs\VanillaX\entities\object\LightningBoltEntity;
use CLADevs\VanillaX\VanillaX;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

class WeatherManager{
    use SingletonTrait;

    /** @var Weather[] */
    private array $weathers = [];

    public function __construct(){
        self::setInstance($this);
    }

    public function startup(): void{
        if(!VanillaX::getInstance()->getConfig()->getNested("features.weather", true)){
            return;
        }
    }

    public function addWeather(World $world): void{
        $this->weathers[strtolower($world->getFolderName())] = new Weather($world);
    }

    public function removeWeather(World $world): void{
        if(isset($this->weathers[strtolower($world->getFolderName())])){
            unset($this->weathers[strtolower($world->getFolderName())]);
        }
    }

    /**
     * @param World|string $world
     * @return Weather|null
     */
    public function getWeather($world): ?Weather{
        $worldName = $world;

        if($world instanceof World){
            $worldName = $world->getFolderName();

            if(!isset($this->weathers[strtolower($worldName)])){
                $this->addWeather($world);
            }
        }
        return $this->weathers[strtolower($worldName)] ?? null;
    }

    public function isRaining(World $world, bool $checkThunder = true): bool{
        $weather = $this->weathers[strtolower($world->getFolderName())] ?? null;

        if($weather !== null){
            return $weather->isRaining() ? true : ($checkThunder ? $weather->isThundering() : false);
        }
        return false;
    }

    public function isThundering(World $world): bool{
        $weather = $this->weathers[strtolower($world->getFolderName())] ?? null;

        if($weather !== null && $weather->isThundering()){
            return true;
        }
        return false;
    }

    /**
     * @param Player[]|Player|null $player
     * @param bool $thunder
     */
    public function sendClear($player = null, bool $thunder = false): void{
        if($player === null){
            $player = Server::getInstance()->getOnlinePlayers();
        }elseif($player instanceof Player){
            $player = [$player];
        }
        foreach($player as $p){
            $pk = new LevelEventPacket();
            $pk->evid = LevelEvent::STOP_RAIN;
            $pk->data = 0;
            $pk->position = new Vector3(0, 0, 0);
            $p->getNetworkSession()->sendDataPacket($pk);

            if($thunder){
                $pk = new LevelEventPacket();
                $pk->evid = LevelEvent::STOP_THUNDER;
                $pk->data = 0;
                $pk->position = new Vector3(0, 0, 0);
                $p->getNetworkSession()->sendDataPacket($pk);
            }
        }
    }

    /**
     * @param Player[]|Player|null $player
     * @param bool $thunder
     */
    public function sendWeather($player = null, bool $thunder = false): void{
        if($player === null){
            $player = Server::getInstance()->getOnlinePlayers();
        }elseif($player instanceof Player){
            $player = [$player];
        }
        foreach($player as $p){
            $pk = new LevelEventPacket();
            $pk->evid = LevelEvent::START_RAIN;
            $pk->data = 65535;
            $pk->position = new Vector3(0, 0, 0);
            $p->getNetworkSession()->sendDataPacket($pk);

            if($thunder){
                $pk = new LevelEventPacket();
                $pk->evid = LevelEvent::START_THUNDER;
                $pk->data = 65535;
                $pk->position = new Vector3(0, 0, 0);
                $p->getNetworkSession()->sendDataPacket($pk);
            }
        }
    }
}