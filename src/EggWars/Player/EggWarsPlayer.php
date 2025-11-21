<?php

declare(strict_types=1);

namespace EggWars\Player;

use pocketmine\player\Player;
use EggWars\Game\Team;

class EggWarsPlayer {
    
    private Player $player;
    private ?Team $team = null;
    private int $kills = 0;
    private int $deaths = 0;
    private int $wins = 0;
    private bool $isAlive = true;
    private bool $isSpectator = false;
    private array $stats = [];
    
    public function __construct(Player $player) {
        $this->player = $player;
    }
    
    public function getPlayer(): Player {
        return $this->player;
    }
    
    public function setTeam(?Team $team): void {
        $this->team = $team;
    }
    
    public function getTeam(): ?Team {
        return $this->team;
    }
    
    public function addKill(): void {
        $this->kills++;
    }
    
    public function setKills(int $kills): void {
        $this->kills = $kills;
    }
    
    public function addDeath(): void {
        $this->deaths++;
        $this->isAlive = false;
    }
    
    public function setDeaths(int $deaths): void {
        $this->deaths = $deaths;
    }
    
    public function respawn(): void {
        $this->isAlive = true;
        if ($this->team !== null && $this->team->hasEgg()) {
            $this->player->teleport($this->team->getSpawnPoint());
            $this->player->setHealth($this->player->getMaxHealth());
            $this->player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
            $this->player->getInventory()->clearAll();
        }
    }
    
    public function setSpectator(bool $spectator): void {
        $this->isSpectator = $spectator;
        $this->isAlive = !$spectator;
    }
    
    public function isSpectator(): bool {
        return $this->isSpectator;
    }
    
    public function eliminate(): void {
        $this->isAlive = false;
        if ($this->team !== null) {
            $this->team->removePlayer($this->player);
        }
    }
    
    public function getKills(): int {
        return $this->kills;
    }
    
    public function getDeaths(): int {
        return $this->deaths;
    }
    
    public function getWins(): int {
        return $this->wins;
    }
    
    public function addWin(): void {
        $this->wins++;
    }
    
    public function setWins(int $wins): void {
        $this->wins = $wins;
    }
    
    public function isAlive(): bool {
        return $this->isAlive;
    }
    
    public function canRespawn(): bool {
        return $this->team !== null && $this->team->hasEgg();
    }
    
    public function reset(): void {
        $this->team = null;
        $this->kills = 0;
        $this->deaths = 0;
        $this->wins = 0;
        $this->isAlive = true;
        $this->stats = [];
    }
}
