<?php

declare(strict_types=1);

namespace EggWars\Shop;

use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\inventory\Inventory;
use EggWars\Main;

class ShopManager {
    
    private Main $plugin;
    private array $shopItems = [];
    private array $categories = [];
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadShopItems();
    }
    
    private function loadShopItems(): void {
        $config = new Config($this->plugin->getDataFolder() . "shop.yml", Config::YAML);
        $shopData = $config->getAll();
        
        foreach ($shopData["categories"] as $categoryName => $categoryData) {
            $this->categories[$categoryName] = $categoryData;
            
            foreach ($categoryData["items"] as $itemData) {
                $item = StringToItemParser::getInstance()->parse($itemData["type"]);
                if ($item === null) {
                    // Fallback for legacy ID system
                    continue;
                }
                $item = $item->setCount($itemData["count"] ?? 1);
                if (isset($itemData["name"])) {
                    $item->setCustomName($itemData["name"]);
                }
                
                $shopItem = new ShopItem(
                    $item,
                    $itemData["cost"],
                    $categoryName,
                    $itemData["display_name"],
                    $itemData["description"] ?? []
                );
                
                $this->shopItems[] = $shopItem;
            }
        }
    }
    
    public function openShop(Player $player): void {
        // This would open a custom inventory UI
        // For simplicity, we'll send a message with available items
        $player->sendMessage(TextFormat::GOLD . "◆ EggWars Shop ◆");
        
        foreach ($this->categories as $categoryName => $categoryData) {
            $player->sendMessage(TextFormat::AQUA . "▸ " . $categoryData["display_name"] . ":");
            
            foreach ($this->getItemsByCategory($categoryName) as $shopItem) {
                $costString = "";
                foreach ($shopItem->getCost() as $resource => $amount) {
                    $resourceName = match($resource) {
                        "iron" => "iron",
                        "gold" => "gold",
                        "diamond" => "diamond",
                        default => $resource
                    };
                    $costString .= TextFormat::YELLOW . $amount . " " . $resourceName . " ";
                }
                
                $player->sendMessage(TextFormat::WHITE . "  • " . $shopItem->getName() . " - " . $costString);
            }
        }
    }
    
    public function purchaseItem(Player $player, string $itemName): bool {
        $shopItem = $this->getItemByName($itemName);
        if ($shopItem === null) {
            $player->sendMessage(TextFormat::RED . "✗ Item not found!");
            return false;
        }
        
        $playerResources = $this->getPlayerResources($player);
        
        if (!$shopItem->canAfford($playerResources)) {
            $player->sendMessage(TextFormat::RED . "✗ You don't have enough resources to purchase!");
            return false;
        }
        
        // Deduct resources
        foreach ($shopItem->getCost() as $resource => $amount) {
            $this->removePlayerResource($player, $resource, $amount);
        }
        
        // Give item
        $player->getInventory()->addItem($shopItem->getItem());
        $player->sendMessage(TextFormat::GREEN . "✓ Purchased: " . TextFormat::AQUA . $shopItem->getName());
        
        return true;
    }
    
    private function getItemsByCategory(string $category): array {
        return array_filter($this->shopItems, fn(ShopItem $item) => $item->getCategory() === $category);
    }
    
    private function getItemByName(string $name): ?ShopItem {
        foreach ($this->shopItems as $shopItem) {
            if (strtolower($shopItem->getName()) === strtolower($name)) {
                return $shopItem;
            }
        }
        return null;
    }
    
    private function getPlayerResources(Player $player): array {
        $resources = ["iron" => 0, "gold" => 0, "diamond" => 0];
        
        foreach ($player->getInventory()->getContents() as $item) {
            if ($item->equals(VanillaItems::IRON_INGOT(), true, false)) {
                $resources["iron"] += $item->getCount();
            } elseif ($item->equals(VanillaItems::GOLD_INGOT(), true, false)) {
                $resources["gold"] += $item->getCount();
            } elseif ($item->equals(VanillaItems::DIAMOND(), true, false)) {
                $resources["diamond"] += $item->getCount();
            }
        }
        
        return $resources;
    }
    
    private function removePlayerResource(Player $player, string $resource, int $amount): void {
        $targetItem = match($resource) {
            "iron" => VanillaItems::IRON_INGOT(),
            "gold" => VanillaItems::GOLD_INGOT(),
            "diamond" => VanillaItems::DIAMOND(),
            default => null
        };
        
        if ($targetItem === null) return;
        
        $inventory = $player->getInventory();
        $remaining = $amount;
        
        foreach ($inventory->getContents() as $slot => $item) {
            if ($item->equals($targetItem, true, false) && $remaining > 0) {
                $toRemove = min($item->getCount(), $remaining);
                $item->setCount($item->getCount() - $toRemove);
                
                if ($item->getCount() <= 0) {
                    $inventory->clear($slot);
                } else {
                    $inventory->setItem($slot, $item);
                }
                
                $remaining -= $toRemove;
            }
        }
    }
}
