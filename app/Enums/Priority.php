<?php

namespace App\Enums;

enum Priority: int
{
    case Low = 1;
    case Normal = 2;
    case High = 3;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
        };
    }

    /**
     * Flux badge color for this priority.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'zinc',
            self::Normal => 'sky',
            self::High => 'red',
        };
    }
}
