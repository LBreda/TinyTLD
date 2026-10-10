<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Role a contact plays for a domain (admin, tech, billing).
 *
 * @property int $id
 * @property int $domain_id
 * @property int $contact_id
 * @property string $role
 */
class ContactDomain extends Pivot
{
    protected $table = 'contact_domain';

    public $incrementing = true;
}
