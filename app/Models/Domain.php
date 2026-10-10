<?php

namespace App\Models;

use App\Enums\ContactRole;
use App\Enums\DomainStatus;
use App\Models\Concerns\HasHostName;
use App\Models\Concerns\LogsRegistryActivity;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $tld_id
 * @property string $name
 * @property string|null $unicode_name
 * @property int $registrant_id
 * @property int|null $registrar_id
 * @property Collection<int, DomainStatus>|null $statuses
 * @property string|null $auth_code
 * @property Carbon|null $registered_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tld $tld
 * @property-read Contact $registrant
 * @property-read Registrar|null $registrar
 */
#[Fillable([
    'tld_id', 'name', 'registrant_id', 'registrar_id', 'statuses',
    'auth_code', 'registered_at', 'expires_at',
])]
#[Hidden(['auth_code'])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, HasHostName, LogsRegistryActivity;

    protected function casts(): array
    {
        return [
            'statuses' => AsEnumCollection::of(DomainStatus::class),
            'auth_code' => 'encrypted',
            'registered_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tld, $this>
     */
    public function tld(): BelongsTo
    {
        return $this->belongsTo(Tld::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function registrant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'registrant_id');
    }

    /**
     * The sponsoring registrar; null for domains managed directly by the registry.
     *
     * @return BelongsTo<Registrar, $this>
     */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }

    /**
     * @return BelongsToMany<Contact, $this, ContactDomain>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->using(ContactDomain::class)->withPivot('id', 'role')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Contact, $this, ContactDomain>
     */
    public function contactsWithRole(ContactRole $role): BelongsToMany
    {
        return $this->contacts()->wherePivot('role', $role->value);
    }

    /**
     * @return BelongsToMany<Nameserver, $this>
     */
    public function nameservers(): BelongsToMany
    {
        return $this->belongsToMany(Nameserver::class)->withTimestamps();
    }

    /**
     * @return HasMany<DsRecord, $this>
     */
    public function dsRecords(): HasMany
    {
        return $this->hasMany(DsRecord::class);
    }

    public function hasStatus(DomainStatus $status): bool
    {
        return (bool) $this->statuses?->contains($status);
    }

    /**
     * Whether the delegation must be published in the zone file.
     */
    public function isPublishedInZone(): bool
    {
        return ! ($this->statuses ?? collect())->contains(fn (DomainStatus $s) => $s->removesFromZone());
    }

    /**
     * Domains visible to the given user: all for admins, otherwise only those
     * sponsored by the user's registrar.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->registrar_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('registrar_id', $user->registrar_id);
    }
}
