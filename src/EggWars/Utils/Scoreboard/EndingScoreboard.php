<?php

declare(strict_types=1);

namespace EggWars\Utils\Scoreboard;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use EggWars\Game\Arena;
use EggWars\Player\EggWarsPlayer;

class EndingScoreboard extends Scoreboard {
    
    protected function getLines(Player $player, ?Arena $arena, EggWarsPlayer $eggWarsPlayer): array {
        // Return empty lines to hide scoreboard during ending state
        return [];
    }
    
    public function show(Player $player, ?Arena $arena, ?EggWarsPlayer $eggWarsPlayer = null): void {
        // Hide scoreboard completely during ending state
        if ($player->isOnline()) {
            $this->hide($player);
        }
    }
    
    private function hide(Player $player): void {
        if ($player->isOnline()) {
            $packet = new \pocketmine\network\mcpe\protocol\RemoveObjectivePacket();
            $packet->objectiveName = $player->getName();
            $player->getNetworkSession()->sendDataPacket($packet);
        }
    }
}
