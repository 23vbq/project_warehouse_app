<?php

namespace App\Enum;

enum ProductType: string
{
    case FINISHED = 'finished';
    case SEMI = 'semi';
    case RAW = 'raw';
    case CONSUMABLES = 'consumables';
}