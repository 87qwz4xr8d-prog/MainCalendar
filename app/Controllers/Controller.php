<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

abstract class Controller
{
    public function __construct(protected Database $db)
    {
    }
}
