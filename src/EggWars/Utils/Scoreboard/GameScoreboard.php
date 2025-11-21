<?php

declare(strict_types=1);

namespace EggWars\Utils\Scoreboard;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use EggWars\Game\Arena;
use EggWars\Player\EggWarsPlayer;

class GameScoreboard extends Scoreboard {
    
    protected function getLines(Player $player, ?Arena $arena, EggWarsPlayer $eggWarsPlayer): array {
        $lines = [];
        
        // Get teams and display them (start from top)
        $teamLines = $this->getTeams($arena, $eggWarsPlayer);
        
        // Use + operator to preserve numeric keys (array_merge reindexes!)
        $lines = $teamLines + $lines;
        
        $lines[11] = "";
        $lines[2] = "";
        $lines[1] = TextFormat::YELLOW . "hexomc.net";
        
        return $lines;
    }
    
    private function getTeams(?Arena $arena, EggWarsPlayer $eggWarsPlayer): array {
        $teams = [];
        $score = 10;
        
        if ($arena === null) {
            return $teams;
        }
        
        $arenaTeams = $arena->getTeams();
        if (empty($arenaTeams)) {
            return $teams;
        }
        
        foreach ($arenaTeams as $teamName => $team) {
            $isPlayerTeam = $eggWarsPlayer->getTeam() === $team;
            $eggStatus = $team->hasEgg() ? TextFormat::GREEN . "✓" : TextFormat::RED . "✗";
            $aliveCount = $team->getAlivePlayersCount();
            $youTag = $isPlayerTeam ? TextFormat::GOLD . " YOU" : "";
            
            $teams[$score] = $team->getColoredName() . " " . $eggStatus . TextFormat::GRAY . " [" . TextFormat::WHITE . $aliveCount . TextFormat::GRAY . "]" . $youTag;
            $score--;
        }
        
        return $teams;
    }
    
}