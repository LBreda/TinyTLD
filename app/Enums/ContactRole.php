<?php

namespace App\Enums;

enum ContactRole: string
{
    case Admin = 'admin';
    case Tech = 'tech';
    case Billing = 'billing';

    /**
     * RDAP entity role (RFC 9083 section 10.2.4).
     */
    public function rdap(): string
    {
        return match ($this) {
            self::Admin => 'administrative',
            self::Tech => 'technical',
            self::Billing => 'billing',
        };
    }
}
