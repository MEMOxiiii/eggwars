<?php

declare(strict_types=1);

namespace EggWars\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use EggWars\Main;

class EggWarsCommand extends Command {
    
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        parent::__construct("eggwars", "EggWars main command", "/eggwars <subcommand>", ["ew"]);
        $this->plugin = $plugin;
        $this->setPermission("eggwars.player");
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "✗ This command is for players only!");
            return false;
        }
        
        if (count($args) === 0) {
            $this->sendHelp($sender);
            return true;
        }
        
        $subCommand = strtolower($args[0]);
        
        switch ($subCommand) {
            case "join":
                return $this->handleJoin($sender, $args);
                
            case "leave":
                return $this->handleLeave($sender);
                
            case "list":
                return $this->handleList($sender);
                
            case "shop":
                return $this->handleShop($sender);
                
            case "stats":
                return $this->handleStats($sender);
                
            case "create":
                return $this->handleCreate($sender, $args);
                
            case "reload":
                return $this->handleReload($sender);
                
            default:
                $this->sendHelp($sender);
                return true;
        }
    }
    
    private function sendHelp(CommandSender $sender): void {
        $sender->sendMessage(TextFormat::GOLD . "◆ EggWars Commands ◆");
        $sender->sendMessage(TextFormat::AQUA . "/ew join <arena> " . TextFormat::WHITE . "- Join an arena");
        $sender->sendMessage(TextFormat::AQUA . "/ew leave " . TextFormat::WHITE . "- Leave the arena");
        $sender->sendMessage(TextFormat::AQUA . "/ew list " . TextFormat::WHITE . "- List arenas");
        $sender->sendMessage(TextFormat::AQUA . "/ew shop " . TextFormat::WHITE . "- Open shop");
        $sender->sendMessage(TextFormat::AQUA . "/ew stats " . TextFormat::WHITE . "- Your statistics");
        
        if ($sender->hasPermission("eggwars.admin")) {
            $sender->sendMessage(TextFormat::RED . "\n◆ Admin Commands ◆");
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "/ew create <arena> " . TextFormat::GRAY . "- Create arena");
            $sender->sendMessage(TextFormat::LIGHT_PURPLE . "/ew reload " . TextFormat::GRAY . "- Reload configuration");
        }
    }
    
    private function handleJoin(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage(TextFormat::RED . "✗ Usage: " . TextFormat::YELLOW . "/ew join <arena>");
            return false;
        }
        
        $arenaName = $args[1];
        $gameManager = $this->plugin->getGameManager();
        
        // Check if player is already in an arena
        if ($gameManager->getPlayerArena($player) !== null) {
            $player->sendMessage(TextFormat::GOLD . "⚠ You are already in an arena! " . TextFormat::YELLOW . "Use " . TextFormat::AQUA . "/ew leave");
            return false;
        }
        
        return $gameManager->joinArena($player, $arenaName);
    }
    
    private function handleLeave(Player $player): bool {
        $gameManager = $this->plugin->getGameManager();
        
        if (!$gameManager->leaveArena($player)) {
            $player->sendMessage(TextFormat::RED . "✗ You are not in any arena!");
            return false;
        }
        
        return true;
    }
    
    private function handleList(Player $player): bool {
        $gameManager = $this->plugin->getGameManager();
        $arenas = $gameManager->getArenas();
        
        if (empty($arenas)) {
            $player->sendMessage(TextFormat::RED . "✗ No arenas available!");
            return true;
        }
        
        $player->sendMessage(TextFormat::GOLD . "◆ Available Arenas ◆");
        
        foreach ($arenas as $arena) {
            $status = match($arena->getState()) {
                \EggWars\Game\GameState::WAITING => TextFormat::GREEN . "Available",
                \EggWars\Game\GameState::STARTING => TextFormat::YELLOW . "Starting",
                \EggWars\Game\GameState::ACTIVE => TextFormat::RED . "Active",
                \EggWars\Game\GameState::ENDING => TextFormat::GRAY . "Ending",
                default => TextFormat::DARK_RED . "Disabled"
            };
            
            $playerCount = $arena->getPlayersCount();
            $player->sendMessage(TextFormat::AQUA . "◆ " . TextFormat::WHITE . $arena->getName() . " - " . $status . TextFormat::WHITE . " (" . $playerCount . " players)");
        }
        
        return true;
    }
    
    private function handleShop(Player $player): bool {
        $arena = $this->plugin->getGameManager()->getPlayerArena($player);
        
        if ($arena === null || $arena->getState() !== \EggWars\Game\GameState::ACTIVE) {
            $player->sendMessage(TextFormat::RED . "✗ You must be in an active game to open the shop!");
            return false;
        }
        
        $this->plugin->getShopManager()->openShop($player);
        return true;
    }
    
    private function handleStats(Player $player): bool {
        $arena = $this->plugin->getGameManager()->getPlayerArena($player);
        
        if ($arena === null) {
            $player->sendMessage(TextFormat::RED . "✗ You are not in any arena!");
            return false;
        }
        
        $eggWarsPlayer = $arena->getPlayer($player->getName());
        if ($eggWarsPlayer === null) {
            return false;
        }
        
        $player->sendMessage(TextFormat::GOLD . "◆ Your Statistics ◆");
        $player->sendMessage(TextFormat::AQUA . "⚔ Kills: " . TextFormat::GREEN . $eggWarsPlayer->getKills());
        $player->sendMessage(TextFormat::AQUA . "☠ Deaths: " . TextFormat::RED . $eggWarsPlayer->getDeaths());
        $player->sendMessage(TextFormat::AQUA . "◆ Team: " . ($eggWarsPlayer->getTeam() ? $eggWarsPlayer->getTeam()->getColoredName() : TextFormat::GRAY . "None"));
        
        return true;
    }
    
    private function handleCreate(Player $player, array $args): bool {
        if (!$player->hasPermission("eggwars.admin")) {
            $player->sendMessage(TextFormat::RED . "✗ You don't have permission for this command!");
            return false;
        }
        
        $player->sendMessage(TextFormat::YELLOW . "◆ Arena creation not available - edit " . TextFormat::AQUA . "arenas.yml " . TextFormat::YELLOW . "manually");
        return true;
    }
    
    private function handleReload(Player $player): bool {
        if (!$player->hasPermission("eggwars.admin")) {
            $player->sendMessage(TextFormat::RED . "✗ You don't have permission for this command!");
            return false;
        }
        
        $this->plugin->reloadConfig();
        $player->sendMessage(TextFormat::GREEN . "✓ Configuration reloaded successfully!");
        return true;
    }
}
