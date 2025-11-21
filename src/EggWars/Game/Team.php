<?php

declare(strict_types=1);

namespace EggWars\Game;

use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

class Team {
    
    private string $name;
    private string $color;
    private Vector3 $spawnPoint;
    private Vector3 $eggLocation;
    private bool $eggAlive = true;
    private array $players = [];
    private array $generators = [];
    
    public function __construct(string $name, string $color, Vector3 $spawnPoint, Vector3 $eggLocation) {
        $this->name = $name;
        $this->color = $color;
        $this->spawnPoint = $spawnPoint;
        $this->eggLocation = $eggLocation;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getColor(): string {
        return $this->color;
    }
    
    public function getColoredName(): string {
        return $this->color . $this->name . TextFormat::RESET;
    }
    
    public function getSpawnPoint(): Vector3 {
        return $this->spawnPoint;
    }
    
    public function getSpawn(): Vector3 {
        return $this->spawnPoint;
    }
    
    public function getEggLocation(): Vector3 {
        return $this->eggLocation;
    }
    
    public function hasEgg(): bool {
        return $this->eggAlive;
    }
    
    public function getEggPosition(): Vector3 {
        return $this->eggLocation;
    }
    
    public function destroyEgg(): void {
        $this->eggAlive = false;
    }
    
    public function addPlayer(Player $player): void {
        $this->players[$player->getName()] = $player;
    }
    
    public function removePlayer(Player $player): void {
        unset($this->players[$player->getName()]);
    }
    
    public function getPlayers(): array {
        return $this->players;
    }
    
    public function getAlivePlayersCount(?Arena $arena = null): int {
        $aliveCount = 0;
        foreach ($this->players as $player) {
            if (!$player->isOnline()) {
                continue;
            }
            
            // Count as alive if:
            // 1. Player is in Survival/Adventure mode
            // 2. Player is in Spectator mode BUT is respawning (temporary spectator)
            $isSpectator = $player->getGamemode()->id() === \pocketmine\player\GameMode::SPECTATOR()->id();
            $isRespawning = $arena !== null && $arena->isPlayerRespawning($player);
            
            if (!$isSpectator || $isRespawning) {
                $aliveCount++;
            }
        }
        return $aliveCount;
    }
    
    public function hasPlayer(Player $player): bool {
        return isset($this->players[$player->getName()]);
    }
    
    public function isEliminated(): bool {
        return !$this->eggAlive && count($this->players) === 0;
    }
    
    public function addGenerator(Vector3 $position, string $type): void {
        $this->generators[] = ["position" => $position, "type" => $type];
    }
    
    public function getGenerators(): array {
        return $this->generators;
    }
}
