<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DsRecord;
use App\Models\Nameserver;
use App\Models\NameserverAddress;
use App\Models\Tld;

/**
 * Builds an RFC 1035 master file for a TLD.
 *
 * Output is deterministic: records are sorted so that two runs over the
 * same data (and serial) produce byte-identical files.
 */
class ZoneFileGenerator
{
    /**
     * Render the zone file using the TLD's current serial.
     */
    public function generate(Tld $tld): string
    {
        $origin = $this->fqdn($tld->name);
        $lines = [
            "; Zone file for {$origin} (serial {$tld->serial})",
            '$ORIGIN '.$origin,
            '$TTL '.$tld->default_ttl,
            '',
            $this->record('@', 'SOA', sprintf(
                '%s %s ( %d %d %d %d %d )',
                $this->fqdn($tld->soa_mname),
                $this->fqdn($this->mailbox($tld->soa_rname)),
                $tld->serial,
                $tld->soa_refresh,
                $tld->soa_retry,
                $tld->soa_expire,
                $tld->soa_minimum,
            )),
        ];

        $apex = collect($tld->apex_nameservers ?? [])
            ->map(fn (string $ns) => $this->fqdn($ns))
            ->unique()
            ->sort()
            ->values();

        foreach ($apex as $ns) {
            $lines[] = $this->record('@', 'NS', $ns);
        }

        /** @var array<string, Nameserver> $glueHosts */
        $glueHosts = [];

        // Glue for in-bailiwick apex nameservers known to the registry.
        $apexHosts = Nameserver::query()
            ->whereIn('name', $apex->map(fn (string $ns) => rtrim($ns, '.'))->all())
            ->with('addresses')
            ->get();

        foreach ($apexHosts as $host) {
            if ($host->isInBailiwick($tld->name)) {
                $glueHosts[$host->name] = $host;
            }
        }

        $delegations = [];

        $domains = $tld->domains()
            ->with(['nameservers.addresses', 'dsRecords'])
            ->orderBy('name')
            ->get();

        foreach ($domains as $domain) {
            /** @var Domain $domain */
            if (! $domain->isPublishedInZone() || $domain->nameservers->isEmpty()) {
                continue;
            }

            $owner = $this->fqdn($domain->name);
            $delegations[] = '';

            foreach ($domain->nameservers->sortBy('name') as $ns) {
                $delegations[] = $this->record($owner, 'NS', $this->fqdn($ns->name));

                if ($ns->isInBailiwick($tld->name)) {
                    $glueHosts[$ns->name] = $ns;
                }
            }

            $dsRecords = $domain->dsRecords->sort(fn (DsRecord $a, DsRecord $b) => [$a->key_tag, $a->algorithm, $a->digest_type, strtoupper($a->digest)]
                <=> [$b->key_tag, $b->algorithm, $b->digest_type, strtoupper($b->digest)]);

            foreach ($dsRecords as $ds) {
                $delegations[] = $this->record($owner, 'DS', sprintf(
                    '%d %d %d %s',
                    $ds->key_tag,
                    $ds->algorithm,
                    $ds->digest_type,
                    strtoupper($ds->digest),
                ));
            }
        }

        array_push($lines, ...$delegations);

        if ($glueHosts !== []) {
            ksort($glueHosts, SORT_STRING);
            $lines[] = '';
            $lines[] = '; Glue records';

            foreach ($glueHosts as $host) {
                $addresses = $host->addresses
                    ->map(fn (NameserverAddress $a) => [$a->version, $this->normalizeIp($a->ip)])
                    ->unique(fn (array $a) => $a[1])
                    ->sort()
                    ->values();

                foreach ($addresses as [$version, $ip]) {
                    $lines[] = $this->record($this->fqdn($host->name), $version === 6 ? 'AAAA' : 'A', $ip);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Advance and persist the SOA serial (YYYYMMDDnn).
     */
    public function bumpSerial(Tld $tld): int
    {
        $tld->serial = $tld->nextSerial();
        $tld->save();

        return $tld->serial;
    }

    private function record(string $owner, string $type, string $data): string
    {
        return sprintf('%-40s IN %-5s %s', $owner, $type, $data);
    }

    private function fqdn(string $name): string
    {
        return rtrim(strtolower(trim($name)), '.').'.';
    }

    /**
     * Convert "hostmaster@nic.example" to the RFC 1035 mailbox form.
     */
    private function mailbox(string $rname): string
    {
        if (! str_contains($rname, '@')) {
            return $rname;
        }

        [$local, $domain] = explode('@', $rname, 2);

        return str_replace('.', '\\.', $local).'.'.$domain;
    }

    private function normalizeIp(string $ip): string
    {
        $packed = @inet_pton($ip);

        return $packed === false ? $ip : (string) inet_ntop($packed);
    }
}
