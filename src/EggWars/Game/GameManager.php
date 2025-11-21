<?php

declare(strict_types=1);

namespace EggWars\Game;

use pocketmine\Server;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use EggWars\Main;
use EggWars\Generator\ResourceGenerator;

class GameManager {
    
    private Main $plugin;
    private array $arenas = [];
    private array $generators = [];
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadArenas();
    }
    
    private function loadArenas(): void {
        $config = new Config($this->plugin->getDataFolder() . "arenas.yml", Config::YAML);
        $arenasData = $config->getAll();
        
        foreach ($arenasData as $arenaName => $data) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
            if ($world === null) {
                $this->plugin->getLogger()->warning("World '{$data["world"]}' for arena '$arenaName' not found!");
                continue;
            }
            
            $arena = new Arena(
                $arenaName,
                $world,
                $data["min_players"],
                $data["max_players"],
                new Vector3($data["lobby_spawn"]["x"], $data["lobby_spawn"]["y"], $data["lobby_spawn"]["z"]),
                new Vector3($data["spectator_spawn"]["x"], $data["spectator_spawn"]["y"], $data["spectator_spawn"]["z"])
            );
            
            // Load teams
            foreach ($data["teams"] as $teamName => $teamData) {
                $team = new Team(
                    $teamName,
                    $teamData["color"],
                    new Vector3($teamData["spawn"]["x"], $teamData["spawn"]["y"], $teamData["spawn"]["z"]),
                    new Vector3($teamData["egg"]["x"], $teamData["egg"]["y"], $teamData["egg"]["z"])
                );
                
                // Add generators for this team
                if (isset($teamData["generators"])) {
                    foreach ($teamData["generators"] as $genData) {
                        $team->addGenerator(
                            new Vector3($genData["x"], $genData["y"], $genData["z"]),
                            $genData["type"]
                        );
                        
                        // Create resource generator
                        $generator = new ResourceGenerator($arena, new Vector3($genData["x"], $genData["y"], $genData["z"]), $genData["type"]);
                        $this->generators[] = $generator;
                    }
                }
                
                $arena->addTeam($team);
            }
            
            // Load center generators (diamond, etc)
            if (isset($data["center_generators"])) {
                foreach ($data["center_generators"] as $genData) {
                    $generator = new ResourceGenerator($arena, new Vector3($genData["x"], $genData["y"], $genData["z"]), $genData["type"]);
                    $this->generators[] = $generator;
                }
            }
            
            $this->arenas[$arenaName] = $arena;
            $this->plugin->getLogger()->info("Loaded arena: $arenaName");
        }
    }
    
    public function getArena(string $name): ?Arena {
        return $this->arenas[$name] ?? null;
    }
    
    public function getArenas(): array {
        return $this->arenas;
    }
    
    public function getGenerators(): array {
        return $this->generators;
    }
    
    public function getPlugin(): Main {
        return $this->plugin;
    }
    
    public function getAvailableArenas(): array {
        return array_filter($this->arenas, fn(Arena $arena) => $arena->getState() === GameState::WAITING);
    }
    
    public function joinArena(Player $player, string $arenaName): bool {
        $arena = $this->getArena($arenaName);
        if ($arena === null) {
            $player->sendMessage(TextFormat::RED . "✗ Arena does not exist!");
            return false;
        }
        
        if ($arena->getState() !== GameState::WAITING) {
            $player->sendMessage(TextFormat::GOLD . "⚠ Arena is not available!");
            return false;
        }
        
        if (!$arena->addPlayer($player)) {
            $player->sendMessage(TextFormat::GOLD . "✗ Arena is full!");
            return false;
        }
        
        $player->sendMessage(TextFormat::AQUA . "✓ Joined arena: " . TextFormat::GREEN . $arenaName);
        
        // Add scoreboard
        $this->plugin->getScoreboardManager()->addScoreboard($player, $arena);
        
        // Force scoreboard update to show correct state
        $this->plugin->getScoreboardManager()->updateAllScoreboards($arena);
        
        return true;
    }
    
    public function leaveArena(Player $player): bool {
        $arena = $this->getPlayerArena($player);
        if ($arena === null) {
            return false;
        }
        
        $arena->removePlayer($player);
        $player->sendMessage(TextFormat::YELLOW . "◆ You left the arena");
        
        // Remove scoreboard
        $this->plugin->getScoreboardManager()->removeScoreboard($player);
        
        // Add lobby scoreboard when returning to main lobby
        $this->plugin->getScoreboardHandler()->addLobbyPlayer($player);
        
        return true;
    }
    
    public function getPlayerArena(Player $player): ?Arena {
        foreach ($this->arenas as $arena) {
            if ($arena->getPlayer($player->getName()) !== null) {
                return $arena;
            }
        }
        return null;
    }
    
    public function broadcastToArena(Arena $arena, string $message): void {
        foreach ($arena->getPlayers() as $eggWarsPlayer) {
            $eggWarsPlayer->getPlayer()->sendMessage($message);
        }
    }
    
    public function tick(): void {
        // Tick all arenas
        foreach ($this->arenas as $arena) {
            $arena->tick();
        }
        
        // Tick all resource generators
        foreach ($this->generators as $generator) {
            $generator->tick();
        }
    }
    
    public function addResourceGenerator(Arena $arena, Vector3 $position, string $type): void {
        $generator = new ResourceGenerator($arena, $position, $type);
        $this->generators[] = $generator;
    }
    
    public function shutdown(): void {
        // Save all player stats before shutdown
        foreach ($this->arenas as $arena) {
            foreach ($arena->getPlayers() as $eggWarsPlayer) {
                $this->plugin->getPlayerDataManager()->savePlayerData($eggWarsPlayer);
                $arena->removePlayer($eggWarsPlayer->getPlayer());
            }
        }
    }
}
