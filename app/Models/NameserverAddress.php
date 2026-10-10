<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $nameserver_id
 * @property string $ip
 * @property int $version
 */
#[Fillable(['ip', 'version'])]
class NameserverAddress extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $address) {
            $address->version = filter_var($address->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 4;
        });
    }

    /**
     * @return BelongsTo<Nameserver, $this>
     */
    public function nameserver(): BelongsTo
    {
        return $this->belongsTo(Nameserver::class);
    }
}
