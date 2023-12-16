<?php

namespace App\Enums;

enum Status: int
{
    case Pending = 0;
    case Completed = 1;
    case Failed = 2;
}
