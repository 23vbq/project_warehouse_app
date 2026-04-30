<?php

namespace App\Enum;

enum ProductType: string
{
    case FINISHED = 'finished';
    case SEMI = 'semi';
    case RAW = 'raw';
    case CONSUMABLES = 'consumables';

    public function label(): string
    {
        return match ($this) {
            self::FINISHED => 'Felgi gotowe',
            self::SEMI => 'Półprodukty',
            self::RAW => 'Surowce',
            self::CONSUMABLES => 'Materiały eksp.',
        };
    }
}
