<?php

declare(strict_types=1);

namespace EggWars\Utils\Scoreboard;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use EggWars\Game\Arena;
use EggWars\Player\EggWarsPlayer;
use EggWars\Game\GameState;

class WaitingScoreboard extends Scoreboard {
    
    protected function getLines(Player $player, ?Arena $arena, EggWarsPlayer $eggWarsPlayer): array {
        $lines = [];
        
        $lines[8] = "";
        $lines[7] = TextFormat::AQUA . "Map: " . TextFormat::GREEN . ($arena ? $arena->getName() : "Unknown");
        $lines[6] = "";
        $lines[5] = TextFormat::GOLD . "Players: " . TextFormat::WHITE . ($arena ? $arena->getPlayersCount() : "0") . TextFormat::GRAY . "/" . TextFormat::WHITE . ($arena ? $arena->getMaxPlayers() : "0");
        $lines[4] = "";
        
        if ($arena && $arena->getState() === GameState::STARTING) {
            $lines[3] = TextFormat::YELLOW . "Starting in: " . TextFormat::GREEN . $arena->getCountdown() . TextFormat::WHITE . "s";
        } else {
            $lines[3] = TextFormat::LIGHT_PURPLE . "Waiting for players...";
        }
        
        $lines[2] = "";
        $lines[1] = TextFormat::YELLOW . "hexomc.net";
        
        return $lines;
    }
}