<?php

namespace App\Enum;

enum OperationStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
}
