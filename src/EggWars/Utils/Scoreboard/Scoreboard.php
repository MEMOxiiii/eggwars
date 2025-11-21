<?php

declare(strict_types=1);

namespace EggWars\Utils\Scoreboard;

use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\utils\TextFormat;
use EggWars\Player\EggWarsPlayer;
use EggWars\Game\Arena;

abstract class Scoreboard {
    
    private const TITLE = TextFormat::BOLD . TextFormat::GOLD . "EGGWARS";
    
    public function show(Player $player, ?Arena $arena, ?EggWarsPlayer $eggWarsPlayer = null): void {
        if ($player->isOnline()) {
            $this->hide($player);
            
            $packet = new SetDisplayObjectivePacket();
            $packet->displaySlot = SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR;
            $packet->objectiveName = $player->getName();
            $packet->displayName = self::TITLE;
            $packet->criteriaName = "dummy";
            $packet->sortOrder = SetDisplayObjectivePacket::SORT_ORDER_DESCENDING;
            $player->getNetworkSession()->sendDataPacket($packet);
            
            if ($eggWarsPlayer === null && $arena !== null) {
                $eggWarsPlayer = $arena->getPlayer($player->getName());
            }
            
            if ($eggWarsPlayer !== null) {
                $lines = $this->getLines($player, $arena, $eggWarsPlayer);
                
                foreach ($lines as $score => $line) {
                    $this->addLine($score, $line, $player);
                }
            }
        }
    }
    
    private function addLine(int $score, string $text, Player $player): void {
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $player->getName();
        $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = $text;
        $entry->score = $score;
        $entry->scoreboardId = $score;
        
        $packet = new SetScorePacket();
        $packet->type = SetScorePacket::TYPE_CHANGE;
        $packet->entries[] = $entry;
        $player->getNetworkSession()->sendDataPacket($packet);
    }
    
    private function hide(Player $player): void {
        if ($player->isOnline()) {
            $packet = new RemoveObjectivePacket();
            $packet->objectiveName = $player->getName();
            $player->getNetworkSession()->sendDataPacket($packet);
        }
    }
    
    public function remove(Player $player): void {
        $this->hide($player);
    }
    
    /**
     * @return array<int, string>
     */
    abstract protected function getLines(Player $player, ?Arena $arena, EggWarsPlayer $eggWarsPlayer): array;
}