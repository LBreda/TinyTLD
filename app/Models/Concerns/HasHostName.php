<?php

namespace App\Models\Concerns;

use App\Support\Idn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stores `name` as lowercase A-label and keeps `unicode_name` in sync.
 *
 * @method static Builder<static> whereHostName(string $name)
 */
trait HasHostName
{
    public static function bootHasHostName(): void
    {
        static::saving(function (self $model) {
            $model->name = Idn::toAscii($model->name);
            $model->unicode_name = Idn::toUnicode($model->name);
        });
    }

    /**
     * Match a host name given either in Unicode or ASCII form.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereHostName(Builder $query, string $name): void
    {
        $query->where('name', Idn::toAscii($name));
    }

    /**
     * Name to display to humans (Unicode when available).
     */
    public function displayName(): string
    {
        return $this->unicode_name ?: $this->name;
    }
}
