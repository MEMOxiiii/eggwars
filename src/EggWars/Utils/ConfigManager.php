<?php

declare(strict_types=1);

namespace EggWars\Utils;

use pocketmine\utils\Config;
use EggWars\Main;

class ConfigManager {
    
    private Main $plugin;
    private Config $config;
    private Config $messages;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->config = $plugin->getConfig();
        $this->messages = new Config($plugin->getDataFolder() . "messages.yml", Config::YAML);
    }
    
    public function getMessage(string $key, array $replacements = []): string {
        $message = $this->messages->get($key, $key);
        
        foreach ($replacements as $search => $replace) {
            $message = str_replace("{" . $search . "}", $replace, $message);
        }
        
        return $message;
    }
    
    public function getConfigValue(string $key, $default = null) {
        return $this->config->get($key, $default);
    }
    
    public function reload(): void {
        $this->plugin->reloadConfig();
        $this->config = $this->plugin->getConfig();
        $this->messages->reload();
    }
    
    public function getLobbySpawnWorld(): string {
        return $this->config->getNested("lobby-spawn.world", "world");
    }
    
    public function getLobbySpawnX(): float {
        return (float) $this->config->getNested("lobby-spawn.x", 0);
    }
    
    public function getLobbySpawnY(): float {
        return (float) $this->config->getNested("lobby-spawn.y", 65);
    }
    
    public function getLobbySpawnZ(): float {
        return (float) $this->config->getNested("lobby-spawn.z", 0);
    }
    
    public function getLobbySpawnPitch(): float {
        return (float) $this->config->getNested("lobby-spawn.pitch", 0.0);
    }
    
    public function getLobbySpawnYaw(): float {
        return (float) $this->config->getNested("lobby-spawn.yaw", 0.0);
    }
}
