<?php

declare(strict_types=1);

namespace EggWars;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use EggWars\Game\GameManager;
use EggWars\Commands\EggWarsCommand;
use EggWars\Events\EventListener;
use EggWars\Utils\ConfigManager;
use EggWars\Utils\ScoreboardManager;
use EggWars\Utils\PlayerDataManager;
use EggWars\Shop\ShopManager;

class Main extends PluginBase {
    
    private static Main $instance;
    private GameManager $gameManager;
    private ConfigManager $configManager;
    private ShopManager $shopManager;
    private ScoreboardManager $scoreboardManager;
    private PlayerDataManager $playerDataManager;
    private \EggWars\Utils\ScoreboardHandler $scoreboardHandler;
    
    public function onEnable(): void {
        self::$instance = $this;
        
        // Save default config files
        $this->saveDefaultConfig();
        $this->saveResource("arenas.yml");
        $this->saveResource("shop.yml");
        $this->saveResource("messages.yml");
        
        // Initialize managers
        $this->configManager = new ConfigManager($this);
        $this->shopManager = new ShopManager($this);
        $this->scoreboardManager = new ScoreboardManager();
        $this->playerDataManager = new PlayerDataManager($this);
        $this->scoreboardHandler = new \EggWars\Utils\ScoreboardHandler($this);
        $this->gameManager = new GameManager($this);
        
        // Register commands
        $this->getServer()->getCommandMap()->register("eggwars", new EggWarsCommand($this));
        
        // Register events
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        
        // Schedule task for arena and generator ticks (every tick = 1/20 second)
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void {
            $this->gameManager->tick();
        }), 1); // Run every tick
        
        // Schedule scoreboard updates (every second = 20 ticks)
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void {
            foreach ($this->gameManager->getArenas() as $arena) {
                foreach ($arena->getPlayers() as $eggWarsPlayer) {
                    $player = $eggWarsPlayer->getPlayer();
                    if ($player !== null && $player->isOnline()) {
                        $this->scoreboardManager->updateScoreboard($player, $arena);
                    }
                }
            }
        }), 20); // Run every second
        
        $this->getLogger()->info("EggWars plugin enabled successfully!");
    }
    
    public function onDisable(): void {
        if (isset($this->gameManager)) {
            $this->gameManager->shutdown();
        }
        $this->getLogger()->info("EggWars plugin disabled!");
    }
    
    public static function getInstance(): Main {
        return self::$instance;
    }
    
    public function getGameManager(): GameManager {
        return $this->gameManager;
    }
    
    public function getConfigManager(): ConfigManager {
        return $this->configManager;
    }
    
    public function getShopManager(): ShopManager {
        return $this->shopManager;
    }
    
    public function getScoreboardManager(): ScoreboardManager {
        return $this->scoreboardManager;
    }
    
    public function getScoreboardHandler(): \EggWars\Utils\ScoreboardHandler {
        return $this->scoreboardHandler;
    }
    
    public function getPlayerDataManager(): PlayerDataManager {
        return $this->playerDataManager;
    }
}
