<?php

declare(strict_types=1);

namespace EggWars\Game;

use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\TextFormat;
use EggWars\Player\EggWarsPlayer;
use EggWars\Main;

class Arena {
    
    private string $name;
    private World $world;
    private GameState $state = GameState::WAITING;
    private array $teams = [];
    private array $players = [];
    private array $spectators = [];
    private array $respawningPlayers = []; // Track players in respawn countdown
    private array $placedBlocks = []; // Track player-placed blocks
    private int $minPlayers;
    private int $maxPlayers;
    private int $countdown = 30;
    private int $tickCounter = 0; // Counter for tick-based timing
    private int $gameTime = 0;
    private Vector3 $lobbySpawn;
    private Vector3 $spectatorSpawn;
    
    public function __construct(string $name, World $world, int $minPlayers, int $maxPlayers, Vector3 $lobbySpawn, Vector3 $spectatorSpawn) {
        $this->name = $name;
        $this->world = $world;
        $this->minPlayers = $minPlayers;
        $this->maxPlayers = $maxPlayers;
        $this->lobbySpawn = $lobbySpawn;
        $this->spectatorSpawn = $spectatorSpawn;
    }
    
    public function getSpectatorSpawn(): Vector3 {
        return $this->spectatorSpawn;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getWorld(): World {
        return $this->world;
    }
    
    public function getState(): GameState {
        return $this->state;
    }
    
    public function setState(GameState $state): void {
        $this->state = $state;
        // Force scoreboard update when state changes
        $this->updateScoreboards();
    }
    
    private function updateScoreboards(): void {
        // This will be called by the plugin's scoreboard manager
        // We need to notify all players that the state has changed
        foreach ($this->players as $eggWarsPlayer) {
            $player = $eggWarsPlayer->getPlayer();
            if ($player->isOnline()) {
                // The ScoreboardManager will handle the actual update
            }
        }
    }
    
    public function addTeam(Team $team): void {
        $this->teams[$team->getName()] = $team;
    }
    
    public function getTeam(string $name): ?Team {
        return $this->teams[$name] ?? null;
    }
    
    public function getTeams(): array {
        return $this->teams;
    }
    
    public function getAliveTeams(): array {
        $aliveTeams = [];
        foreach ($this->teams as $team) {
            if ($team->hasEgg() || $team->getAlivePlayersCount($this) > 0) {
                $aliveTeams[] = $team;
            }
        }
        return $aliveTeams;
    }
    
    public function addPlayer(Player $player): bool {
        if (count($this->players) >= $this->maxPlayers) {
            return false;
        }
        
        $eggWarsPlayer = new EggWarsPlayer($player);
        
        // Load player stats from storage
        Main::getInstance()->getPlayerDataManager()->loadPlayerData($eggWarsPlayer);
        
        $this->players[$player->getName()] = $eggWarsPlayer;
        $player->teleport($this->lobbySpawn);
        
        // Set lobby gamemode to Adventure and full health/hunger
        $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
        $player->getHungerManager()->setSaturation(20.0);
        
        $this->broadcastMessage(TextFormat::GREEN . "◆ " . TextFormat::AQUA . $player->getName() . TextFormat::GREEN . " joined the game! " . TextFormat::WHITE . "(" . $this->getPlayersCount() . "/" . $this->maxPlayers . ")");
        
        // Check if we can start
        if ($this->state === GameState::WAITING && $this->getPlayersCount() >= $this->minPlayers) {
            $this->startCountdown();
        }
        
        return true;
    }
    
    public function removePlayer(Player $player): void {
        $playerName = $player->getName();
        
        if (isset($this->players[$playerName])) {
            $eggWarsPlayer = $this->players[$playerName];
            
            // Save player stats before removal
            Main::getInstance()->getPlayerDataManager()->savePlayerData($eggWarsPlayer);
            if ($eggWarsPlayer->getTeam() !== null) {
                $eggWarsPlayer->getTeam()->removePlayer($player);
            }
            unset($this->players[$playerName]);
        }
        
        unset($this->spectators[$playerName]);
        
        if ($this->state === GameState::STARTING && $this->getPlayersCount() < $this->minPlayers) {
            $this->setState(GameState::WAITING);
            $this->countdown = 30;
            $this->broadcastMessage(TextFormat::RED . "✗ Countdown cancelled! " . TextFormat::YELLOW . "Need at least " . $this->minPlayers . " players");
        }
    }
    
    public function getPlayer(string $name): ?EggWarsPlayer {
        return $this->players[$name] ?? null;
    }
    
    public function getPlayers(): array {
        return $this->players;
    }
    
    public function getPlayersCount(): int {
        return count($this->players);
    }
    
    public function getMaxPlayers(): int {
        return $this->maxPlayers;
    }
    
    public function getCountdown(): int {
        return $this->countdown;
    }
    
    public function canStart(): bool {
        return $this->getPlayersCount() >= $this->minPlayers && $this->state === GameState::WAITING;
    }
    
    public function startCountdown(): void {
        $this->setState(GameState::STARTING);
        $this->countdown = 30;
        $this->tickCounter = 0; // Reset tick counter to prevent skipping
    }
    
    public function startGame(): void {
        $this->setState(GameState::ACTIVE);
        $this->tickCounter = 0; // Reset tick counter for game time tracking
        $this->assignPlayersToTeams();
        $this->teleportPlayersToSpawns();
        $this->placeTeamEggs();
        $this->gameTime = 0;
        
        // Set all players to Survival mode for active gameplay
        foreach ($this->players as $eggWarsPlayer) {
            $player = $eggWarsPlayer->getPlayer();
            if ($player !== null && $player->isOnline()) {
                $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
                $player->sendMessage(TextFormat::GREEN . "⚔ Game started! " . TextFormat::GOLD . "Protect your egg and destroy enemy eggs!");
            }
        }
    }
    
    private function placeTeamEggs(): void {
        foreach ($this->teams as $team) {
            if ($team->hasEgg()) {
                $this->world->setBlock($team->getEggLocation(), VanillaBlocks::DRAGON_EGG());
            }
        }
    }
    
    public function endGame(?Team $winner = null): void {
        $this->setState(GameState::ENDING);
        
        // Award wins to the winning team players and save all player stats
        if ($winner !== null) {
            foreach ($this->players as $eggWarsPlayer) {
                if ($eggWarsPlayer->getTeam() === $winner) {
                    $eggWarsPlayer->addWin();
                }
                // Save stats immediately after awarding wins
                Main::getInstance()->getPlayerDataManager()->savePlayerData($eggWarsPlayer);
            }
        } else {
            // Even if no winner, save all player stats
            foreach ($this->players as $eggWarsPlayer) {
                Main::getInstance()->getPlayerDataManager()->savePlayerData($eggWarsPlayer);
            }
        }
        
        // Broadcast winner message
        $message = $winner ? TextFormat::GOLD . "★ Team " . $winner->getColoredName() . TextFormat::GOLD . " won the game! ★" : TextFormat::GRAY . "Game ended with no winner!";
        foreach ($this->players as $eggWarsPlayer) {
            $eggWarsPlayer->getPlayer()->sendMessage($message);
        }
        
        // Clear items from generators immediately when game ends
        $this->clearGeneratorItems();
        
        // Reset arena after 10 seconds
        // This would typically be handled by a task scheduler
    }
    
    public function reset(): void {
        // Save all player stats before clearing
        foreach ($this->players as $eggWarsPlayer) {
            Main::getInstance()->getPlayerDataManager()->savePlayerData($eggWarsPlayer);
        }
        
        $this->state = GameState::WAITING;
        $this->players = [];
        $this->spectators = [];
        $this->respawningPlayers = []; // Clear respawning players list
        
        // Clear player-built blocks immediately
        $this->clearPlayerBuiltBlocks();
        
        // Clear items from generators
        $this->clearGeneratorItems();
        
        $this->placedBlocks = [];
        $this->countdown = 30;
        $this->gameTime = 0;
        
        // Reset teams and place eggs
        foreach ($this->teams as $teamName => $team) {
            $newTeam = new Team($team->getName(), $team->getColor(), $team->getSpawnPoint(), $team->getEggLocation());
            $this->teams[$teamName] = $newTeam;
            // Place the egg back
            $this->world->setBlock($team->getEggLocation(), VanillaBlocks::DRAGON_EGG());
        }
    }
    
    public function clearPlayerBuiltBlocks(): void {
        // Only clear blocks that were actually placed by players during the game
        // This prevents clearing natural island blocks
        foreach ($this->placedBlocks as $blockKey => $value) {
            $coords = explode(":", $blockKey);
            if (count($coords) === 3) {
                $x = (int)$coords[0];
                $y = (int)$coords[1]; 
                $z = (int)$coords[2];
                $position = new \pocketmine\math\Vector3($x, $y, $z);
                
                // Clear the player-placed block
                $this->world->setBlock($position, \pocketmine\block\VanillaBlocks::AIR());
            }
        }
    }
    
    public function clearGeneratorItems(): void {
        // Remove all ItemEntity objects from the arena world
        foreach ($this->world->getEntities() as $entity) {
            if ($entity instanceof \pocketmine\entity\object\ItemEntity) {
                $entity->flagForDespawn();
            }
        }
    }
    
    public function addPlacedBlock(Vector3 $position): void {
        // Use floor to handle decimal coordinates properly
        $key = (int)floor($position->x) . ":" . (int)floor($position->y) . ":" . (int)floor($position->z);
        $this->placedBlocks[$key] = true;
    }
    
    public function canBreakBlock(Vector3 $position): bool {
        // Use floor to handle decimal coordinates properly
        $key = (int)floor($position->x) . ":" . (int)floor($position->y) . ":" . (int)floor($position->z);
        return isset($this->placedBlocks[$key]);
    }
    
    public function removePlacedBlock(Vector3 $position): void {
        // Use floor to handle decimal coordinates properly
        $key = (int)floor($position->x) . ":" . (int)floor($position->y) . ":" . (int)floor($position->z);
        unset($this->placedBlocks[$key]);
    }
    
    private function assignPlayersToTeams(): void {
        $players = array_values($this->players);
        $teams = array_values($this->teams);
        $teamIndex = 0;
        
        foreach ($players as $eggWarsPlayer) {
            $team = $teams[$teamIndex % count($teams)];
            $eggWarsPlayer->setTeam($team);
            $team->addPlayer($eggWarsPlayer->getPlayer());
            $teamIndex++;
        }
    }
    
    private function teleportPlayersToSpawns(): void {
        foreach ($this->players as $eggWarsPlayer) {
            $team = $eggWarsPlayer->getTeam();
            if ($team !== null) {
                $eggWarsPlayer->getPlayer()->teleport($team->getSpawnPoint());
            }
        }
    }
    
    public function getLobbySpawn(): Vector3 {
        return $this->lobbySpawn;
    }
    

    
    public function addSpectator(Player $player): void {
        $this->spectators[$player->getName()] = $player;
        $player->teleport($this->spectatorSpawn);
        $player->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
    }
    
    public function getSpectators(): array {
        return $this->spectators;
    }
    
    public function addRespawningPlayer(Player $player): void {
        $this->respawningPlayers[$player->getName()] = $player;
    }
    
    public function removeRespawningPlayer(Player $player): void {
        unset($this->respawningPlayers[$player->getName()]);
    }
    
    public function isPlayerRespawning(Player $player): bool {
        return isset($this->respawningPlayers[$player->getName()]);
    }
    
    public function tick(): void {
        $this->tickCounter++;
        
        switch ($this->state) {
            case GameState::WAITING:
                if ($this->getPlayersCount() >= $this->minPlayers) {
                    $this->setState(GameState::STARTING);
                    $this->countdown = 30;
                    $this->tickCounter = 0; // Reset tick counter
                    $this->broadcastMessage(TextFormat::GREEN . "◆ Game will start in " . TextFormat::GOLD . $this->countdown . TextFormat::GREEN . " seconds!");
                }
                break;
                
            case GameState::STARTING:
                // Only countdown every 20 ticks (1 second)
                if ($this->tickCounter >= 20) {
                    $this->tickCounter = 0;
                    $this->countdown--;
                    
                    if ($this->countdown <= 0) {
                        $this->startGame();
                    } elseif ($this->countdown % 10 == 0 || $this->countdown <= 5) {
                        $color = $this->countdown <= 5 ? TextFormat::RED : TextFormat::YELLOW;
                        $this->broadcastMessage($color . "⏱ Game starting in " . TextFormat::LIGHT_PURPLE . $this->countdown . $color . " seconds!");
                    }
                }
                break;
                
            case GameState::ACTIVE:
                // Only increment game time every 20 ticks (1 second)
                if ($this->tickCounter >= 20) {
                    $this->tickCounter = 0;
                    $this->gameTime++;
                }
                $this->checkWinCondition();
                break;
                
            case GameState::ENDING:
                // Handle game ending
                break;
        }
    }
    
    private function checkWinCondition(): void {
        $aliveTeams = $this->getAliveTeams();
        
        if (count($aliveTeams) <= 1) {
            $winner = count($aliveTeams) === 1 ? array_values($aliveTeams)[0] : null;
            $this->endGame($winner);
        }
    }
    
    public function broadcastMessage(string $message): void {
        foreach ($this->players as $eggWarsPlayer) {
            $eggWarsPlayer->getPlayer()->sendMessage($message);
        }
    }
}
