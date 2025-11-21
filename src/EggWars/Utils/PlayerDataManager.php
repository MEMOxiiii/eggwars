<?php

declare(strict_types=1);

namespace EggWars\Utils;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use EggWars\Main;
use EggWars\Player\EggWarsPlayer;

class PlayerDataManager {
    
    private Main $plugin;
    private Config $playerData;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->playerData = new Config($plugin->getDataFolder() . "playerdata.yml", Config::YAML);
    }
    
    public function loadPlayerData(EggWarsPlayer $eggWarsPlayer): void {
        $playerName = $eggWarsPlayer->getPlayer()->getName();
        $data = $this->playerData->get($playerName, [
            "kills" => 0,
            "deaths" => 0,
            "wins" => 0
        ]);
        
        // Set the loaded stats directly
        $eggWarsPlayer->setKills($data["kills"]);
        $eggWarsPlayer->setDeaths($data["deaths"]);
        $eggWarsPlayer->setWins($data["wins"]);
    }
    
    public function savePlayerData(EggWarsPlayer $eggWarsPlayer): void {
        $playerName = $eggWarsPlayer->getPlayer()->getName();
        $this->playerData->set($playerName, [
            "kills" => $eggWarsPlayer->getKills(),
            "deaths" => $eggWarsPlayer->getDeaths(),
            "wins" => $eggWarsPlayer->getWins()
        ]);
        $this->playerData->save();
    }
    
    public function getPlayerStats(string $playerName): array {
        return $this->playerData->get($playerName, [
            "kills" => 0,
            "deaths" => 0,
            "wins" => 0
        ]);
    }
}
