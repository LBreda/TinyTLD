<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DNSSEC delegation signer record (RFC 4034).
 *
 * @property int $id
 * @property int $domain_id
 * @property int $key_tag
 * @property int $algorithm
 * @property int $digest_type
 * @property string $digest
 */
#[Fillable(['key_tag', 'algorithm', 'digest_type', 'digest'])]
class DsRecord extends Model
{
    protected function casts(): array
    {
        return [
            'key_tag' => 'integer',
            'algorithm' => 'integer',
            'digest_type' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
