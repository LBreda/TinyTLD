<?php

namespace App\Models;

use App\Models\Concerns\HasHostName;
use App\Models\Concerns\LogsRegistryActivity;
use Database\Factories\NameserverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $unicode_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Nameserver extends Model
{
    /** @use HasFactory<NameserverFactory> */
    use HasFactory, HasHostName, LogsRegistryActivity;

    /**
     * @return HasMany<NameserverAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(NameserverAddress::class);
    }

    /**
     * @return BelongsToMany<Domain, $this>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class)->withTimestamps();
    }

    /**
     * Whether the host lives inside the given zone, so glue records are needed.
     */
    public function isInBailiwick(string $zone): bool
    {
        return str_ends_with($this->name, '.'.ltrim($zone, '.'));
    }
}
