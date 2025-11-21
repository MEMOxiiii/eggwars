<?php

declare(strict_types=1);

namespace EggWars\Generator;

use pocketmine\math\Vector3;
use pocketmine\item\VanillaItems;
use pocketmine\entity\object\ItemEntity;
use EggWars\Game\Arena;

class ResourceGenerator {
    
    private Arena $arena;
    private Vector3 $position;
    private string $type;
    private int $tickCounter = 0;
    private int $interval;
    private int $maxItems;
    
    public function __construct(Arena $arena, Vector3 $position, string $type) {
        $this->arena = $arena;
        $this->position = $position;
        $this->type = $type;
        
        // Set intervals based on resource type
        $this->interval = match($type) {
            "iron" => 20,    // 1 second
            "gold" => 100,   // 5 seconds
            "diamond" => 300 // 15 seconds
        };
        
        $this->maxItems = 8; // Maximum items that can exist at generator
    }
    
    public function tick(): void {
        if ($this->arena->getState() !== \EggWars\Game\GameState::ACTIVE) {
            return;
        }
        
        $this->tickCounter++;
        
        if ($this->tickCounter >= $this->interval) {
            $this->generateResource();
            $this->tickCounter = 0;
        }
    }
    
    private function generateResource(): void {
        // Check if there are already too many items at this location
        $world = $this->arena->getWorld();
        $bb = new \pocketmine\math\AxisAlignedBB(
            $this->position->x - 2, $this->position->y - 1, $this->position->z - 2,
            $this->position->x + 2, $this->position->y + 3, $this->position->z + 2
        );
        $nearbyEntities = $world->getNearbyEntities($bb);
        
        $itemCount = 0;
        foreach ($nearbyEntities as $entity) {
            if ($entity instanceof ItemEntity) {
                $itemCount++;
            }
        }
        
        if ($itemCount >= $this->maxItems) {
            return;
        }
        
        $item = $this->createResourceItem();
        if ($item !== null) {
            $spawnPos = $this->position->add(0.5, 1, 0.5); // Spawn above generator
            
            // Use world->dropItem instead of manually creating ItemEntity to avoid duplication
            try {
                $world->dropItem($spawnPos, $item);
            } catch (\Exception $e) {
                // Silently ignore if item can't be dropped (prevents crash)
            }
        }
    }
    
    private function createResourceItem(): ?\pocketmine\item\Item {
        return match($this->type) {
            "iron" => VanillaItems::IRON_INGOT(),
            "gold" => VanillaItems::GOLD_INGOT(),
            "diamond" => VanillaItems::DIAMOND(),
            default => null
        };
    }
    
    public function getPosition(): Vector3 {
        return $this->position;
    }
    
    public function getType(): string {
        return $this->type;
    }
}
