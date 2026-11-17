<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Done => 'Done',
        };
    }

    /**
     * Flux badge color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::OnHold => 'amber',
            self::Done => 'zinc',
        };
    }
}
