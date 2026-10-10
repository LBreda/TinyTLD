<?php

namespace App\Models;

use App\Models\Concerns\HasHostName;
use App\Models\Concerns\LogsRegistryActivity;
use Database\Factories\TldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $unicode_name
 * @property int $default_ttl
 * @property string $soa_mname
 * @property string $soa_rname
 * @property int $soa_refresh
 * @property int $soa_retry
 * @property int $soa_expire
 * @property int $soa_minimum
 * @property int $serial
 * @property list<string>|null $apex_nameservers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'default_ttl', 'soa_mname', 'soa_rname', 'soa_refresh',
    'soa_retry', 'soa_expire', 'soa_minimum', 'serial', 'apex_nameservers',
])]
class Tld extends Model
{
    /** @use HasFactory<TldFactory> */
    use HasFactory, HasHostName, LogsRegistryActivity;

    protected function casts(): array
    {
        return [
            'apex_nameservers' => 'array',
            'default_ttl' => 'integer',
            'serial' => 'integer',
        ];
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Bump the SOA serial using the YYYYMMDDnn convention.
     */
    public function nextSerial(): int
    {
        $today = (int) now()->format('Ymd00');

        return $this->serial >= $today ? $this->serial + 1 : $today;
    }
}
