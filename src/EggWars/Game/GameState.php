<?php

declare(strict_types=1);

namespace EggWars\Game;

enum GameState: string {
    case WAITING = "waiting";
    case STARTING = "starting";
    case ACTIVE = "active";
    case ENDING = "ending";
    case DISABLED = "disabled";
}
