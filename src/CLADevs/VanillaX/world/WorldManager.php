<?php

namespace CLADevs\VanillaX\world;

use CLADevs\VanillaX\world\weather\WeatherManager;
use pocketmine\utils\SingletonTrait;

class WorldManager{
    use SingletonTrait;

    private WeatherManager $weatherManager;

    public function __construct(){
        self::setInstance($this);
        $this->weatherManager = new WeatherManager();
    }

    public function startup(): void{
        $this->weatherManager->startup();
    }

    public function getWeatherManager(): WeatherManager{
        return $this->weatherManager;
    }
}