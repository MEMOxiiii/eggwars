<?php

declare(strict_types=1);

namespace EggWars\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\entity\EntityInteractEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\TextFormat;
use EggWars\Main;
use EggWars\Game\GameState;
use EggWars\Game\Arena;

class EventListener implements Listener {
    
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $gameManager = $this->plugin->getGameManager();
        
        // If player is not in any arena, teleport to lobby and set main lobby status
        if ($gameManager->getPlayerArena($player) === null) {
            // Set main lobby gamemode to Adventure (prevents block interaction)
            $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
            $player->getHungerManager()->setSaturation(20.0);
            
            // Clear inventory in lobby
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            
            // Teleport to configurable lobby spawn
            $configManager = $this->plugin->getConfigManager();
            $worldName = $configManager->getLobbySpawnWorld();
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
            if ($world !== null) {
                $x = $configManager->getLobbySpawnX();
                $y = $configManager->getLobbySpawnY();
                $z = $configManager->getLobbySpawnZ();
                $pitch = $configManager->getLobbySpawnPitch();
                $yaw = $configManager->getLobbySpawnYaw();
                $player->teleport(new \pocketmine\world\Position($x, $y, $z, $world), $yaw, $pitch);
            }
            
            $this->plugin->getScoreboardHandler()->addLobbyPlayer($player);
        }
    }
    
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $gameManager = $this->plugin->getGameManager();
        
        $arena = $gameManager->getPlayerArena($player);
        if ($arena !== null) {
            $gameManager->leaveArena($player);
        }
        
        // Remove scoreboard
        $this->plugin->getScoreboardManager()->removeScoreboard($player);
    }
    
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }
        
        $player = $entity;
        $gameManager = $this->plugin->getGameManager();
        
        $arena = $gameManager->getPlayerArena($player);
        if ($arena === null) {
            // If player is in main lobby, prevent all damage and maintain Adventure mode
            $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
            $player->getHungerManager()->setSaturation(20.0);
            $event->cancel();
            return;
        }
        
        // Prevent damage in lobby and maintain full health/hunger
        if ($arena->getState() === GameState::WAITING || $arena->getState() === GameState::STARTING || $arena->getState() === GameState::ENDING) {
            $event->cancel();
            
            // Ensure player maintains proper mode and full health/hunger in lobby and during game ending
            // DO NOT change spectator mode back to Adventure for players who are already spectators
            if ($player->getGamemode() !== \pocketmine\player\GameMode::SPECTATOR()) {
                $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
            }
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
            $player->getHungerManager()->setSaturation(20.0);
            return;
        }
        
        // Only handle death prevention in active games
        if ($arena->getState() !== GameState::ACTIVE) {
            return;
        }
        
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer === null) {
            return;
        }
        
        // Handle team damage (prevent friendly fire)
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if ($damager instanceof Player) {
                $damagerData = $arena->getPlayer($damager->getName());
                
                if ($damagerData !== null) {
                    $playerTeam = $eggWarsPlayer->getTeam();
                    $damagerTeam = $damagerData->getTeam();
                    
                    // Prevent friendly fire
                    if ($playerTeam !== null && $damagerTeam !== null && $playerTeam === $damagerTeam) {
                        $event->cancel();
                        $damager->sendMessage(TextFormat::RED . "✗ You cannot attack teammates!");
                        return;
                    }
                }
            }
        }
        
        // **ULTIMATE FIX: Prevent death by canceling fatal damage**
        if ($event->getFinalDamage() >= $player->getHealth()) {
            $event->cancel();
            
            // Handle death manually without death screen
            $eggWarsPlayer->addDeath();
            
            // Handle killer stats
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if ($damager instanceof Player) {
                    $killerArena = $gameManager->getPlayerArena($damager);
                    if ($killerArena === $arena) {
                        $killerPlayer = $arena->getPlayer($damager->getName());
                        if ($killerPlayer !== null) {
                            $killerPlayer->addKill();
                            $damager->sendMessage(TextFormat::GREEN . "⚔ Killed " . TextFormat::GOLD . $player->getName());
                        }
                    }
                }
            }
            
            // Check if team has egg - if not, become spectator
            $team = $eggWarsPlayer->getTeam();
            if ($team !== null && !$team->hasEgg()) {
                $player->sendMessage(TextFormat::RED . "✗ Your team's egg is destroyed! " . TextFormat::GRAY . "You are now a spectator");
                $this->makeSpectator($player, $arena);
                
                // Check for win condition after making player spectator
                $this->checkWinCondition($arena);
                return;
            }
            
            // If team has egg, start respawn countdown
            $player->sendMessage(TextFormat::GOLD . "☠ " . TextFormat::YELLOW . "You will respawn soon...");
            $this->startRespawnCountdown($player, $arena);
        }
    }
    
    private function startRespawnCountdown(Player $player, Arena $arena): void {
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer === null) {
            return;
        }
        
        // Register player as respawning (prevents false win detection)
        $arena->addRespawningPlayer($player);
        
        // Teleport to spectator spawn first
        $spectatorSpawn = $arena->getSpectatorSpawn();
        if ($spectatorSpawn !== null) {
            $spectatorPos = new \pocketmine\world\Position($spectatorSpawn->x, $spectatorSpawn->y, $spectatorSpawn->z, $arena->getWorld());
            $player->teleport($spectatorPos);
            $player->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
        }
        
        // Clear inventory during respawn
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        
        // Start 5-second countdown
        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($player, $arena, $this->plugin) extends \pocketmine\scheduler\Task {
            private int $countdown = 5;
            private Player $player;
            private Arena $arena;
            private \EggWars\Main $plugin;
            
            public function __construct(Player $player, Arena $arena, \EggWars\Main $plugin) {
                $this->player = $player;
                $this->arena = $arena;
                $this->plugin = $plugin;
            }
            
            public function onRun(): void {
                if (!$this->player->isOnline() || $this->plugin->getGameManager()->getPlayerArena($this->player) !== $this->arena) {
                    $this->arena->removeRespawningPlayer($this->player);
                    $this->getHandler()->cancel();
                    return;
                }
                
                if ($this->countdown > 0) {
                    // Send countdown message in chat
                    $this->player->sendMessage(TextFormat::AQUA . "◆ Respawning in: " . TextFormat::LIGHT_PURPLE . $this->countdown . TextFormat::AQUA . " seconds");
                    
                    // Send title countdown
                    $this->player->sendTitle(
                        TextFormat::GOLD . "Respawning",
                        TextFormat::YELLOW . "in " . TextFormat::RED . $this->countdown . TextFormat::YELLOW . " seconds",
                        10, 20, 10
                    );
                    
                    $this->countdown--;
                } else {
                    // Check if arena is still ACTIVE before respawning
                    if ($this->arena->getState() !== \EggWars\Game\GameState::ACTIVE) {
                        $this->arena->removeRespawningPlayer($this->player);
                        $this->getHandler()->cancel();
                        return;
                    }
                    
                    // Respawn the player
                    $this->respawnPlayer();
                    $this->arena->removeRespawningPlayer($this->player);
                    $this->getHandler()->cancel();
                }
            }
            
            private function respawnPlayer(): void {
                $eggWarsPlayer = $this->arena->getPlayer($this->player->getName());
                if ($eggWarsPlayer === null) {
                    return;
                }
                
                $team = $eggWarsPlayer->getTeam();
                if ($team !== null) {
                    $teamSpawn = $team->getSpawn();
                    if ($teamSpawn !== null) {
                        $teamSpawnPos = new \pocketmine\world\Position($teamSpawn->x, $teamSpawn->y, $teamSpawn->z, $this->arena->getWorld());
                        
                        // Heal player and teleport
                        $this->player->setHealth($this->player->getMaxHealth());
                        $this->player->getHungerManager()->setFood($this->player->getHungerManager()->getMaxFood());
                        $this->player->teleport($teamSpawnPos);
                        $this->player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
                        
                        // Clear inventory and give basic items
                        $this->player->getInventory()->clearAll();
                        $this->player->getArmorInventory()->clearAll();
                        
                        // Success messages
                        $this->player->sendMessage(TextFormat::GREEN . "✓ You have respawned successfully!");
                        $this->player->sendTitle(
                            TextFormat::GREEN . "Respawned!",
                            TextFormat::AQUA . "Return to battle",
                            10, 40, 10
                        );
                        
                        // Warn if egg was destroyed during countdown
                        if (!$team->hasEgg()) {
                            $this->player->sendMessage(TextFormat::GOLD . "⚠ " . TextFormat::RED . "Your egg is destroyed! " . TextFormat::YELLOW . "This is your final life!");
                        }
                    }
                }
            }
        }, 20); // Run every second (20 ticks)
    }
    
    public function onPlayerRespawn(PlayerRespawnEvent $event): void {
        $player = $event->getPlayer();
        $gameManager = $this->plugin->getGameManager();
        
        $arena = $gameManager->getPlayerArena($player);
        if ($arena === null || $arena->getState() !== GameState::ACTIVE) {
            return;
        }
        
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer === null) {
            return;
        }
        
        // Check if player should become spectator
        $team = $eggWarsPlayer->getTeam();
        if ($team !== null && !$team->hasEgg()) {
            // Teleport to spectator spawn
            $spectatorSpawn = $arena->getSpectatorSpawn();
            if ($spectatorSpawn !== null) {
                $spectatorPos = new \pocketmine\world\Position($spectatorSpawn->x, $spectatorSpawn->y, $spectatorSpawn->z, $arena->getWorld());
                $event->setRespawnPosition($spectatorPos);
                $player->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
                $player->sendMessage(TextFormat::RED . "✗ You are now a spectator!");
            }
            return;
        }
        
        // If team has egg, respawn at team spawn
        if ($team !== null) {
            $teamSpawn = $team->getSpawn();
            if ($teamSpawn !== null) {
                $teamSpawnPos = new \pocketmine\world\Position($teamSpawn->x, $teamSpawn->y, $teamSpawn->z, $arena->getWorld());
                $event->setRespawnPosition($teamSpawnPos);
                $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());
                $player->sendMessage(TextFormat::GREEN . "✓ You have respawned successfully!");
            }
        }
    }
    
    private function makeSpectator(Player $player, Arena $arena): void {
        // Add to spectators list
        $arena->addSpectator($player);
        
        // Set spectator mode
        $player->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
        
        // Teleport to spectator spawn - convert Vector3 to Position with world
        $spectatorSpawn = $arena->getSpectatorSpawn();
        $spectatorPos = new \pocketmine\world\Position($spectatorSpawn->x, $spectatorSpawn->y, $spectatorSpawn->z, $arena->getWorld());
        $player->teleport($spectatorPos);
        
        // Clear inventory
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        
        // Remove from game
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer !== null) {
            $eggWarsPlayer->setSpectator(true);
        }
        
        $player->sendMessage(TextFormat::GRAY . "◆ You are now a spectator - you can observe the players");
    }
    
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $gameManager = $this->plugin->getGameManager();
        
        $arena = $gameManager->getPlayerArena($player);
        if ($arena === null) {
            // Player is in main lobby - CANCEL to protect lobby
            $event->cancel();
            return;
        }
        
        // CANCEL breaking in waiting lobby and game ending states to prevent griefing
        if ($arena->getState() === GameState::WAITING || $arena->getState() === GameState::STARTING || $arena->getState() === GameState::ENDING) {
            $event->cancel();
            return;
        }
        
        // Only allow breaking in ACTIVE state
        if ($arena->getState() !== GameState::ACTIVE) {
            $event->cancel();
            return;
        }
        
        // Check for DRAGON_EGG first - always handle egg breaking
        if ($block->getTypeId() === VanillaBlocks::DRAGON_EGG()->getTypeId()) {
            $event->cancel();
            $this->handleEggBreak($player, $arena, $block);
            return;
        }
        
        // Get exact block position
        $blockPos = $block->getPosition();
        
        // Check if this block was placed by a player - ALWAYS allow breaking tracked blocks
        if ($arena->canBreakBlock($blockPos)) {
            // Allow breaking and remove from tracking
            $arena->removePlacedBlock($blockPos);
            return;
        }
        
        // Otherwise cancel - not a placed block, must be natural terrain
        $event->cancel();
    }
    
    private function handleEggBreak(Player $player, \EggWars\Game\Arena $arena, \pocketmine\block\Block $block): void {
        $playerTeam = null;
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer !== null) {
            $playerTeam = $eggWarsPlayer->getTeam();
        }
        
        foreach ($arena->getTeams() as $team) {
            $eggPos = $team->getEggPosition();
            $blockPos = $block->getPosition();

            
            // Check if positions match (with tolerance for floating point)
            $distance = sqrt(
                pow($eggPos->x - $blockPos->x, 2) + 
                pow($eggPos->y - $blockPos->y, 2) + 
                pow($eggPos->z - $blockPos->z, 2)
            );
            
            if ($distance < 1.5 && $team->hasEgg()) {
                // Don't let players break their own team's egg
                if ($playerTeam !== null && $playerTeam === $team) {
                    $player->sendMessage(TextFormat::RED . "✗ You cannot destroy your team's egg!");
                    return;
                }
                
                // Destroy the egg block immediately
                $arena->getWorld()->setBlock($block->getPosition(), VanillaBlocks::AIR());
                $team->destroyEgg();
                $arena->broadcastMessage(TextFormat::RED . "⚠ " . TextFormat::GOLD . $player->getName() . TextFormat::RED . " destroyed " . $team->getColoredName() . TextFormat::RED . "'s egg!");
                
                // Send special title message to affected team members only
                foreach ($arena->getPlayers() as $eggWarsPlayer) {
                    if ($eggWarsPlayer !== null && $eggWarsPlayer->getTeam() === $team) {
                        $playerObj = $eggWarsPlayer->getPlayer();
                        if ($playerObj !== null && $playerObj->isOnline()) {
                            // Send simple title message to team members whose egg was destroyed
                            $playerObj->sendTitle(
                                TextFormat::RED . TextFormat::BOLD . "Egg Destroyed!",
                                TextFormat::YELLOW . "You cannot respawn after death",
                                20, 40, 20
                            );
                        }
                    }
                }
                
                // Give gold to player
                $player->getInventory()->addItem(VanillaItems::GOLD_INGOT()->setCount(5));
                $player->sendMessage(TextFormat::GOLD . "◆ You received " . TextFormat::YELLOW . "5 gold " . TextFormat::GOLD . "for destroying the egg!");
                
                // Check for win condition after egg destruction
                $this->checkWinCondition($arena);
                
                return;
            }
        }
        
        // No egg found at this position
    }
    
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $gameManager = $this->plugin->getGameManager();
        
        $arena = $gameManager->getPlayerArena($player);
        if ($arena === null) {
            // Player is in main lobby - CANCEL to protect lobby
            $event->cancel();
            return;
        }
        
        // CANCEL placing in waiting lobby and game ending states to prevent griefing
        if ($arena->getState() === GameState::WAITING || $arena->getState() === GameState::STARTING || $arena->getState() === GameState::ENDING) {
            $event->cancel();
            return;
        }
        
        // Allow block placing during active game and track it for cleanup
        if ($arena->getState() === GameState::ACTIVE) {
            // Track all blocks in the transaction (PocketMine API 5 uses transactions)
            $transaction = $event->getTransaction();
            foreach ($transaction->getBlocks() as $blockData) {
                // $blockData is array [int $x, int $y, int $z, Block $block]
                $block = $blockData[3]; // Extract Block object from array
                $blockPos = $block->getPosition();
                $arena->addPlacedBlock($blockPos);
            }
        } else {
            // Cancel placing in any other state
            $event->cancel();
        }
    }
    
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $gameManager = $this->plugin->getGameManager();
        
        $arena = $gameManager->getPlayerArena($player);
        if ($arena === null || $arena->getState() !== GameState::ACTIVE) {
            return;
        }
        
        // Handle egg interaction (prevent teleporting AND break egg)
        if ($block->getTypeId() === VanillaBlocks::DRAGON_EGG()->getTypeId()) {
            $event->cancel(); // Prevent normal dragon egg teleporting behavior
            $this->handleEggBreak($player, $arena, $block);
            return;
        }
        
        // Handle shop interaction (chest or sign)
        if ($block->getTypeId() === VanillaBlocks::CHEST()->getTypeId()) {
            $event->cancel();
            $this->plugin->getShopManager()->openShop($player);
            return;
        }
        
        // No debug messages needed
    }
    
    private function checkWinCondition(Arena $arena): void {
        $alivePlayers = [];
        $aliveTeams = [];
        
        // Count alive players and their teams
        foreach ($arena->getPlayers() as $eggWarsPlayer) {
            if ($eggWarsPlayer !== null && !$eggWarsPlayer->isSpectator()) {
                $player = $eggWarsPlayer->getPlayer();
                if ($player !== null && $player->isOnline()) {
                    $alivePlayers[] = $eggWarsPlayer;
                    $team = $eggWarsPlayer->getTeam();
                    if ($team !== null && !in_array($team, $aliveTeams, true)) {
                        $aliveTeams[] = $team;
                    }
                }
            }
        }
        
        // Check win conditions - use team count, not player count
        if (count($aliveTeams) <= 1) {
            // Determine winning team
            $winningTeam = null;
            if (count($aliveTeams) === 1) {
                $winningTeam = $aliveTeams[0];
            }
            
            // Announce victory
            if ($winningTeam !== null) {
                $arena->broadcastMessage(TextFormat::GOLD . "★ Team " . $winningTeam->getColoredName() . TextFormat::GOLD . " won the game! ★");
                
                // Send victory message to all winning team members
                foreach ($alivePlayers as $alivePlayer) {
                    if ($alivePlayer->getTeam() === $winningTeam) {
                        $playerObj = $alivePlayer->getPlayer();
                        if ($playerObj !== null && $playerObj->isOnline()) {
                            $playerObj->sendTitle(
                                TextFormat::GOLD . TextFormat::BOLD . "★ VICTORY ★",
                                TextFormat::AQUA . "Your team won the game!",
                                20, 60, 20
                            );
                            
                            // Clear winner's inventory immediately after victory
                            $playerObj->getInventory()->clearAll();
                            $playerObj->getArmorInventory()->clearAll();
                        }
                    }
                }
            } else {
                // No players left (shouldn't happen but just in case)
                $arena->broadcastMessage(TextFormat::GRAY . "Game ended with no winner!");
            }
            
            // End the game
            $arena->setState(\EggWars\Game\GameState::ENDING);
            
            // Set game modes based on winner/loser status
            foreach ($arena->getPlayers() as $eggWarsPlayer) {
                $player = $eggWarsPlayer->getPlayer();
                if ($player !== null && $player->isOnline()) {
                    // Check if this player is on the winning team and is alive (not a spectator)
                    if ($winningTeam !== null && $eggWarsPlayer->getTeam() === $winningTeam && !$eggWarsPlayer->isSpectator()) {
                        // Winner: Set to Adventure mode with full health
                        $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
                        $player->setHealth($player->getMaxHealth());
                        $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
                        $player->getHungerManager()->setSaturation(20.0);
                    } else {
                        // Loser or spectator: Keep in Spectator mode
                        $player->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
                    }
                }
            }
            
            // Keep spectators in Spectator mode
            foreach ($arena->getSpectators() as $spectatorName => $spectator) {
                if ($spectator !== null && $spectator->isOnline()) {
                    $spectator->setGamemode(\pocketmine\player\GameMode::SPECTATOR());
                }
            }
            
            // Reset arena blocks immediately when game ends
            $arena->clearPlayerBuiltBlocks();
            
            // Send all players back to lobby after 8 seconds with Adventure mode
            $this->plugin->getScheduler()->scheduleDelayedTask(new class($arena, $this->plugin) extends \pocketmine\scheduler\Task {
                private Arena $arena;
                private Main $plugin;
                
                public function __construct(Arena $arena, Main $plugin) {
                    $this->arena = $arena;
                    $this->plugin = $plugin;
                }
                
                public function onRun(): void {
                    // Send all players back to main lobby with Adventure mode
                    foreach ($this->arena->getPlayers() as $eggWarsPlayer) {
                        $player = $eggWarsPlayer->getPlayer();
                        if ($player !== null && $player->isOnline()) {
                            // Remove from arena first (this also adds lobby scoreboard)
                            $this->plugin->getGameManager()->leaveArena($player);
                            
                            // Set Adventure mode and full health/hunger for lobby
                            $player->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
                            $player->setHealth($player->getMaxHealth());
                            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
                            $player->getHungerManager()->setSaturation(20.0);
                            
                            // Clear inventory and give lobby items
                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            
                            // Teleport to main lobby spawn from config
                            $configManager = $this->plugin->getConfigManager();
                            $worldName = $configManager->getLobbySpawnWorld();
                            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
                            if ($world !== null) {
                                $x = $configManager->getLobbySpawnX();
                                $y = $configManager->getLobbySpawnY();
                                $z = $configManager->getLobbySpawnZ();
                                $pitch = $configManager->getLobbySpawnPitch();
                                $yaw = $configManager->getLobbySpawnYaw();
                                $player->teleport(new \pocketmine\world\Position($x, $y, $z, $world), $yaw, $pitch);
                            } else {
                                $player->teleport($player->getWorld()->getSpawnLocation());
                            }
                            
                            $player->sendMessage(TextFormat::GREEN . "Game ended! You have been returned to the lobby.");
                        }
                    }
                    
                    // Also handle spectators
                    foreach ($this->arena->getSpectators() as $spectatorName => $spectator) {
                        if ($spectator !== null && $spectator->isOnline()) {
                            // Remove from arena (this also adds lobby scoreboard)
                            $this->plugin->getGameManager()->leaveArena($spectator);
                            
                            // Set Adventure mode and full health/hunger for lobby
                            $spectator->setGamemode(\pocketmine\player\GameMode::ADVENTURE());
                            $spectator->setHealth($spectator->getMaxHealth());
                            $spectator->getHungerManager()->setFood($spectator->getHungerManager()->getMaxFood());
                            $spectator->getHungerManager()->setSaturation(20.0);
                            
                            // Clear inventory
                            $spectator->getInventory()->clearAll();
                            $spectator->getArmorInventory()->clearAll();
                            
                            // Teleport to main lobby spawn from config
                            $configManager = $this->plugin->getConfigManager();
                            $worldName = $configManager->getLobbySpawnWorld();
                            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
                            if ($world !== null) {
                                $x = $configManager->getLobbySpawnX();
                                $y = $configManager->getLobbySpawnY();
                                $z = $configManager->getLobbySpawnZ();
                                $pitch = $configManager->getLobbySpawnPitch();
                                $yaw = $configManager->getLobbySpawnYaw();
                                $spectator->teleport(new \pocketmine\world\Position($x, $y, $z, $world), $yaw, $pitch);
                            } else {
                                $spectator->teleport($spectator->getWorld()->getSpawnLocation());
                            }
                            
                            $spectator->sendMessage(TextFormat::GREEN . "Game ended! You have been returned to the lobby.");
                        }
                    }
                    
                    // Reset the arena
                    $this->arena->reset();
                }
            }, 160); // 8 seconds = 160 ticks
        }
    }

}
