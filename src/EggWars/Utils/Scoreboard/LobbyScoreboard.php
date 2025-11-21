<?php

declare(strict_types=1);

namespace EggWars\Utils\Scoreboard;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use EggWars\Game\Arena;
use EggWars\Player\EggWarsPlayer;
use EggWars\Main;

class LobbyScoreboard extends Scoreboard {
    
    protected function getLines(Player $player, ?Arena $arena, EggWarsPlayer $eggWarsPlayer): array {
        // Read stats directly from file to avoid stale in-memory values
        $playerData = Main::getInstance()->getPlayerDataManager()->getPlayerStats($player->getName());
        
        $kills = $playerData['kills'] ?? 0;
        $deaths = $playerData['deaths'] ?? 0;
        $wins = $playerData['wins'] ?? 0;
        
        $lines = [];
        
        $lines[8] = "";
        $lines[7] = TextFormat::GOLD . "Kills: " . TextFormat::GREEN . $kills;
        $lines[6] = "";
        $lines[5] = TextFormat::AQUA . "Deaths: " . TextFormat::RED . $deaths;
        $lines[4] = "";
        $lines[3] = TextFormat::LIGHT_PURPLE . "Wins: " . TextFormat::GREEN . $wins;
        $lines[2] = "";
        $lines[1] = TextFormat::YELLOW . "hexomc.net";
        
        return $lines;
    }
    
    public function show(Player $player, ?Arena $arena, ?EggWarsPlayer $eggWarsPlayer = null): void {
        if ($player->isOnline()) {
            $this->hide($player);
            
            $packet = new \pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket();
            $packet->displaySlot = \pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR;
            $packet->objectiveName = $player->getName();
            $packet->displayName = \pocketmine\utils\TextFormat::BOLD . \pocketmine\utils\TextFormat::GOLD . "EGGWARS";
            $packet->criteriaName = "dummy";
            $packet->sortOrder = \pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket::SORT_ORDER_DESCENDING;
            $player->getNetworkSession()->sendDataPacket($packet);
            
            $lines = $this->getLines($player, $arena, $eggWarsPlayer);
            
            foreach ($lines as $score => $line) {
                $this->addLine($score, $line, $player);
            }
        }
    }
    
    private function addLine(int $score, string $text, Player $player): void {
        $entry = new \pocketmine\network\mcpe\protocol\types\ScorePacketEntry();
        $entry->objectiveName = $player->getName();
        $entry->type = \pocketmine\network\mcpe\protocol\types\ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = $text;
        $entry->score = $score;
        $entry->scoreboardId = $score;
        
        $packet = new \pocketmine\network\mcpe\protocol\SetScorePacket();
        $packet->type = \pocketmine\network\mcpe\protocol\SetScorePacket::TYPE_CHANGE;
        $packet->entries[] = $entry;
        $player->getNetworkSession()->sendDataPacket($packet);
    }
    
    private function hide(Player $player): void {
        if ($player->isOnline()) {
            $packet = new \pocketmine\network\mcpe\protocol\RemoveObjectivePacket();
            $packet->objectiveName = $player->getName();
            $player->getNetworkSession()->sendDataPacket($packet);
        }
    }
}