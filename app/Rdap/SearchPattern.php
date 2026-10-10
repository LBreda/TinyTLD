<?php

namespace App\Rdap;

use App\Models\Concerns\HasHostName;
use App\Support\Idn;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * RDAP search pattern (RFC 9082 section 4.1): an exact host name or a
 * name whose first label ends with a single "*" wildcard, e.g. "exa*"
 * or "exa*.example". Unicode and A-label input are both accepted.
 */
final class SearchPattern
{
    private function __construct(
        public readonly ?string $exact,
        public readonly string $prefix,
        public readonly ?string $suffix,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public static function parse(string $pattern): self
    {
        $pattern = mb_strtolower(rtrim(trim($pattern), '.'));

        if ($pattern === '') {
            throw new InvalidArgumentException('Empty search pattern.');
        }

        $wildcards = substr_count($pattern, '*');

        if ($wildcards === 0) {
            if (! Idn::isValidHostName($pattern)) {
                throw new InvalidArgumentException('Invalid host name.');
            }

            return new self(Idn::toAscii($pattern), '', null);
        }

        if ($wildcards > 1) {
            throw new InvalidArgumentException('Only one wildcard is allowed.');
        }

        [$prefix, $rest] = explode('*', $pattern, 2);

        if ($rest !== '' && ! str_starts_with($rest, '.')) {
            throw new InvalidArgumentException('The wildcard must end a label.');
        }

        // Only letters, digits, marks, hyphens and dots: this also rules
        // out the LIKE metacharacters "%" and "_" without driver-specific escaping.
        if (! preg_match('/^[\p{L}\p{N}\p{M}.-]*$/u', $prefix)) {
            throw new InvalidArgumentException('Invalid characters in search pattern.');
        }

        $suffix = null;

        if ($rest !== '') {
            $suffix = substr($rest, 1);

            if (! Idn::isValidHostName($suffix)) {
                throw new InvalidArgumentException('Invalid host name suffix.');
            }

            $suffix = Idn::toAscii($suffix);
        }

        if ($prefix === '' && $suffix === null) {
            throw new InvalidArgumentException('Search pattern is too broad.');
        }

        return new self(null, $prefix, $suffix);
    }

    /**
     * Restrict a query on a model using {@see HasHostName}.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        if ($this->exact !== null) {
            return $query->where('name', $this->exact);
        }

        if ($this->prefix !== '') {
            $prefix = $this->prefix;

            $query->where(function (Builder $query) use ($prefix) {
                if (preg_match('/^[a-z0-9.-]+$/', $prefix)) {
                    $query->orWhere('name', 'like', $prefix.'%');
                }

                $query->orWhere('unicode_name', 'like', $prefix.'%');
            });
        }

        if ($this->suffix !== null) {
            $query->where('name', 'like', '%.'.$this->suffix);
        }

        return $query;
    }
}
