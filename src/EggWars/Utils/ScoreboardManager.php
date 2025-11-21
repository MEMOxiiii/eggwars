<?php

declare(strict_types=1);

namespace EggWars\Utils;

use pocketmine\player\Player;
use EggWars\Game\Arena;
use EggWars\Game\GameState;
use EggWars\Utils\Scoreboard\WaitingScoreboard;
use EggWars\Utils\Scoreboard\LobbyScoreboard;
use EggWars\Utils\Scoreboard\GameScoreboard;
use EggWars\Utils\Scoreboard\Scoreboard;

class ScoreboardManager {
    
    private array $scoreboards = [];
    
    public function addScoreboard(Player $player, Arena $arena): void {
        $this->scoreboards[$player->getName()] = $arena;
        $this->updateScoreboard($player, $arena);
    }
    
    public function removeScoreboard(Player $player): void {
        $playerName = $player->getName();
        if (isset($this->scoreboards[$playerName])) {
            $scoreboard = $this->getScoreboardForState($this->scoreboards[$playerName]->getState());
            $scoreboard->remove($player);
            unset($this->scoreboards[$playerName]);
        }
    }
    
    public function updateScoreboard(Player $player, Arena $arena): void {
        $playerName = $player->getName();
        if (!isset($this->scoreboards[$playerName])) {
            return;
        }
        
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer === null) {
            return;
        }
        
        $scoreboard = $this->getScoreboardForState($arena->getState());
        $scoreboard->show($player, $arena, $eggWarsPlayer);
    }
    
    public function updateAllScoreboards(Arena $arena): void {
        foreach ($arena->getPlayers() as $eggWarsPlayer) {
            $player = $eggWarsPlayer->getPlayer();
            if ($player->isOnline()) {
                $this->updateScoreboard($player, $arena);
            }
        }
    }
    
    private function getScoreboardForState(GameState $state): Scoreboard {
        return match($state) {
            GameState::WAITING => new WaitingScoreboard(),
            GameState::STARTING => new WaitingScoreboard(),
            GameState::ACTIVE => new GameScoreboard(),
            GameState::ENDING => new \EggWars\Utils\Scoreboard\EndingScoreboard(), // Hide scoreboard during ending
            default => new LobbyScoreboard(),
        };
    }
}