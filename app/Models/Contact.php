<?php

namespace App\Models;

use App\Enums\ContactKind;
use App\Models\Concerns\LogsRegistryActivity;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Registrant or other domain contact.
 *
 * @property int $id
 * @property string $handle
 * @property ContactKind $kind
 * @property string $name
 * @property string|null $organization
 * @property string|null $street
 * @property string|null $city
 * @property string|null $state_province
 * @property string|null $postal_code
 * @property string $country_code
 * @property string $email
 * @property string|null $phone
 * @property string|null $fax
 * @property list<string>|null $disclose
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContactDomain|null $pivot Present when loaded through Domain::contacts().
 */
#[Fillable([
    'handle', 'kind', 'name', 'organization', 'street', 'city', 'state_province',
    'postal_code', 'country_code', 'email', 'phone', 'fax', 'disclose',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, LogsRegistryActivity;

    /**
     * Fields that may be published via RDAP when listed in `disclose`.
     */
    public const DISCLOSABLE = ['name', 'organization', 'address', 'email', 'phone', 'fax'];

    protected function casts(): array
    {
        return [
            'kind' => ContactKind::class,
            'disclose' => 'array',
        ];
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function registeredDomains(): HasMany
    {
        return $this->hasMany(Domain::class, 'registrant_id');
    }

    /**
     * @return BelongsToMany<Domain, $this, ContactDomain>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class)->using(ContactDomain::class)->withPivot('id', 'role')->withTimestamps();
    }

    /**
     * Whether the given field may be published.
     */
    public function discloses(string $field): bool
    {
        return in_array($field, $this->disclose ?? [], true);
    }

    /**
     * Contacts visible to the given user: everything for admins, otherwise
     * only contacts attached to domains sponsored by the user's registrar.
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

        $query->where(function (Builder $query) use ($user) {
            $query->whereHas('registeredDomains', fn (Builder $q) => $q->where('registrar_id', $user->registrar_id))
                ->orWhereHas('domains', fn (Builder $q) => $q->where('registrar_id', $user->registrar_id));
        });
    }
}
