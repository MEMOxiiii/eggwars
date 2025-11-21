<?php

declare(strict_types=1);

namespace EggWars\Utils;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use EggWars\Main;
use EggWars\Utils\Scoreboard\LobbyScoreboard;

class ScoreboardHandler {
    
    private Main $plugin;
    private array $lobbyPlayers = [];
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function addLobbyPlayer(Player $player): void {
        $this->lobbyPlayers[$player->getName()] = $player;
        $this->showLobbyScoreboard($player);
    }
    
    public function removeLobbyPlayer(Player $player): void {
        $playerName = $player->getName();
        if (isset($this->lobbyPlayers[$playerName])) {
            unset($this->lobbyPlayers[$playerName]);
            $this->plugin->getScoreboardManager()->removeScoreboard($player);
        }
    }
    
    private function showLobbyScoreboard(Player $player): void {
        $scoreboard = new LobbyScoreboard();
        // Create a dummy arena for lobby display
        $eggWarsPlayer = new \EggWars\Player\EggWarsPlayer($player);
        
        $scoreboard->show($player, null, $eggWarsPlayer);
    }
    
    public function isLobbyPlayer(Player $player): bool {
        return isset($this->lobbyPlayers[$player->getName()]);
    }
}