<?php

namespace App\Entity;

enum ItemListPosition: string
{
    case First = 'first';
    case Last = 'last';

    public function label(): string
    {
        return match ($this) {
            self::First => 'Beginning',
            self::Last => 'End',
        };
    }
}
