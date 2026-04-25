<?php

namespace App\Enums;

enum UserRole: string
{
    case User   = 'user';
    case Critic = 'critic';
    case Admin  = 'admin';
}
