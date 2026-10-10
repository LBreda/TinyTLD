<?php

namespace App\Models;

use App\Enums\RegistrarStatus;
use App\Models\Concerns\LogsRegistryActivity;
use Database\Factories\RegistrarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A registrar is a company, not a login: its operators are {@see User}s.
 *
 * @property int $id
 * @property string $handle
 * @property int|null $iana_id
 * @property string $name
 * @property string|null $organization
 * @property string $email
 * @property string|null $phone
 * @property string|null $url
 * @property string|null $abuse_email
 * @property string|null $abuse_phone
 * @property string|null $street
 * @property string|null $city
 * @property string|null $state_province
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property RegistrarStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'handle', 'iana_id', 'name', 'organization', 'email', 'phone', 'url',
    'abuse_email', 'abuse_phone', 'street', 'city', 'state_province',
    'postal_code', 'country_code', 'status',
])]
class Registrar extends Model
{
    /** @use HasFactory<RegistrarFactory> */
    use HasFactory, LogsRegistryActivity;

    protected function casts(): array
    {
        return [
            'iana_id' => 'integer',
            'status' => RegistrarStatus::class,
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
