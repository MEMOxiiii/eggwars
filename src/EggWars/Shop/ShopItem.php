<?php

declare(strict_types=1);

namespace EggWars\Shop;

use pocketmine\item\Item;

class ShopItem {
    
    private Item $item;
    private array $cost;
    private string $category;
    private string $name;
    private array $description;
    
    public function __construct(Item $item, array $cost, string $category, string $name, array $description = []) {
        $this->item = $item;
        $this->cost = $cost;
        $this->category = $category;
        $this->name = $name;
        $this->description = $description;
    }
    
    public function getItem(): Item {
        return clone $this->item;
    }
    
    public function getCost(): array {
        return $this->cost;
    }
    
    public function getCategory(): string {
        return $this->category;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getDescription(): array {
        return $this->description;
    }
    
    public function canAfford(array $playerResources): bool {
        foreach ($this->cost as $resource => $amount) {
            if (!isset($playerResources[$resource]) || $playerResources[$resource] < $amount) {
                return false;
            }
        }
        return true;
    }
}
