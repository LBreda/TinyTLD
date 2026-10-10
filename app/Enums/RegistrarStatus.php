<?php

namespace App\Enums;

enum RegistrarStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
