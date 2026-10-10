<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Nameserver;
use App\Models\Tld;
use App\Services\ZoneFileGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneFileTest extends TestCase
{
    use RefreshDatabase;

    private Tld $tld;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tld = Tld::factory()->create([
            'name' => 'test',
            'default_ttl' => 3600,
            'soa_mname' => 'a.nic.test',
            'soa_rname' => 'hostmaster.nic.test',
            'serial' => 2026010100,
            'apex_nameservers' => ['b.nic.test', 'a.nic.test'],
        ])->fresh();
    }

    /**
     * @param  list<string>  $nameservers
     * @param  list<DomainStatus>  $statuses
     */
    private function delegate(string $name, array $nameservers, array $statuses = []): Domain
    {
        $domain = Domain::factory()->create(['tld_id' => $this->tld->id, 'name' => $name, 'statuses' => $statuses]);

        foreach ($nameservers as $host) {
            $domain->nameservers()->attach(Nameserver::query()->whereHostName($host)->first() ?? Nameserver::factory()->create(['name' => $host]));
        }

        return $domain;
    }

    /**
     * @return list<string> records with whitespace collapsed
     */
    private function records(string $zone): array
    {
        return array_map(fn (string $line) => preg_replace('/\s+/', ' ', trim($line)), explode("\n", $zone));
    }

    public function test_zone_contains_header_soa_and_apex_ns(): void
    {
        $records = $this->records(app(ZoneFileGenerator::class)->generate($this->tld));

        $this->assertContains('$ORIGIN test.', $records);
        $this->assertContains('$TTL 3600', $records);
        $this->assertContains('@ IN SOA a.nic.test. hostmaster.nic.test. ( 2026010100 7200 3600 1209600 3600 )', $records);

        $apex = array_values(array_filter($records, fn (string $r) => str_starts_with($r, '@ IN NS')));
        $this->assertSame(['@ IN NS a.nic.test.', '@ IN NS b.nic.test.'], $apex);
    }

    public function test_zone_contains_delegations_ds_and_in_bailiwick_glue(): void
    {
        $inZone = Nameserver::factory()->create(['name' => 'ns1.example.test']);
        $inZone->addresses()->create(['ip' => '192.0.2.1']);
        $inZone->addresses()->create(['ip' => '2001:DB8:0:0::1']);
        $outOfZone = Nameserver::factory()->create(['name' => 'ns.provider.net']);
        $outOfZone->addresses()->create(['ip' => '198.51.100.1']);

        $domain = $this->delegate('example.test', ['ns1.example.test', 'ns.provider.net']);
        $domain->dsRecords()->create(['key_tag' => 2371, 'algorithm' => 13, 'digest_type' => 2, 'digest' => 'abcdef']);
        $this->delegate('caffè.test', ['ns1.example.test']);

        $zone = app(ZoneFileGenerator::class)->generate($this->tld);
        $records = $this->records($zone);

        $this->assertContains('example.test. IN NS ns.provider.net.', $records);
        $this->assertContains('example.test. IN NS ns1.example.test.', $records);
        $this->assertContains('example.test. IN DS 2371 13 2 ABCDEF', $records);
        $this->assertContains('xn--caff-8oa.test. IN NS ns1.example.test.', $records);
        $this->assertContains('ns1.example.test. IN A 192.0.2.1', $records);
        $this->assertContains('ns1.example.test. IN AAAA 2001:db8::1', $records);
        $this->assertStringNotContainsString('198.51.100.1', $zone);

        // Glue appears once even though two domains use the host.
        $this->assertSame(1, substr_count($zone, '192.0.2.1'));

        // Deterministic ordering.
        $this->assertLessThan(strpos($zone, 'xn--caff-8oa.test.'), strpos($zone, 'example.test. '));
        $this->assertSame($zone, app(ZoneFileGenerator::class)->generate($this->tld->fresh()));
    }

    public function test_held_and_undelegated_domains_are_excluded(): void
    {
        $this->delegate('live.test', ['ns.provider.net']);
        $this->delegate('client-held.test', ['ns.provider.net'], [DomainStatus::ClientHold]);
        $this->delegate('server-held.test', ['ns.provider.net'], [DomainStatus::ServerHold]);
        $this->delegate('locked.test', ['ns.provider.net'], [DomainStatus::ServerTransferProhibited]);
        $this->delegate('empty.test', []);

        $zone = app(ZoneFileGenerator::class)->generate($this->tld);

        $this->assertStringContainsString('live.test.', $zone);
        $this->assertStringContainsString('locked.test.', $zone);
        $this->assertStringNotContainsString('client-held.test', $zone);
        $this->assertStringNotContainsString('server-held.test', $zone);
        $this->assertStringNotContainsString('empty.test', $zone);
    }

    public function test_apex_glue_is_included_for_known_registry_nameservers(): void
    {
        Nameserver::factory()->create(['name' => 'a.nic.test'])->addresses()->create(['ip' => '203.0.113.1']);

        $records = $this->records(app(ZoneFileGenerator::class)->generate($this->tld));

        $this->assertContains('a.nic.test. IN A 203.0.113.1', $records);
    }

    public function test_command_bumps_serial_and_writes_file(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9));
        $path = tempnam(sys_get_temp_dir(), 'zone');

        $this->artisan('registry:zone', ['tld' => 'test', '--output' => $path])->assertSuccessful();

        $this->assertSame(2026100900, $this->tld->fresh()->serial);
        $this->assertStringContainsString('( 2026100900 ', (string) file_get_contents($path));

        $this->artisan('registry:zone', ['tld' => 'TEST.'])
            ->expectsOutputToContain('2026100901')
            ->assertSuccessful();

        $this->assertSame(2026100901, $this->tld->fresh()->serial);

        $this->artisan('registry:zone', ['tld' => 'nope'])->assertFailed();

        @unlink($path);
    }
}
