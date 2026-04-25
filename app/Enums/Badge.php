<?php

namespace App\Enums;

enum Badge: string
{
    case Verified          = 'verificado';
    case FastCritic        = 'critico-rapido';
    case NoviceCritic      = 'critico-novel';
    case JuniorCritic      = 'critico-junior';
    case SeniorCritic      = 'critico-senior';
    case MasterCritic      = 'critico-maestro';
    case TheCritic         = 'el-critico';
    case FriendCritic      = 'critico-amigo';
    case SoughtCritic      = 'critico-solicitado';
    case ReliableCritic    = 'critico-fiable';
    case FamousCritic      = 'critico-famoso';
    case InfluentialCritic = 'critico-influyente';
}
