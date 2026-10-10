<?php

namespace Tests\Feature;

use App\Enums\ContactRole;
use App\Enums\DomainStatus;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Nameserver;
use App\Models\Registrar;
use App\Models\Tld;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RdapTest extends TestCase
{
    use RefreshDatabase;

    private Tld $tld;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tld = Tld::factory()->create(['name' => 'test']);
    }

    private function makeDomain(string $name, array $attributes = []): Domain
    {
        return Domain::factory()->create(['tld_id' => $this->tld->id, 'name' => $name, ...$attributes]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rdap(TestResponse $response): array
    {
        $response->assertHeader('Content-Type', 'application/rdap+json');
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>|null
     */
    private function entityWithRole(array $object, string $role): ?array
    {
        foreach ($object['entities'] ?? [] as $entity) {
            if (in_array($role, $entity['roles'] ?? [], true)) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function vcardProperties(array $entity): array
    {
        return array_map(fn (array $p) => $p[0], $entity['vcardArray'][1]);
    }

    public function test_domain_lookup_by_unicode_and_a_label(): void
    {
        $domain = $this->makeDomain('caffè.test', [
            'statuses' => [DomainStatus::ClientTransferProhibited, DomainStatus::ServerHold],
        ]);
        $ns = Nameserver::factory()->create(['name' => 'ns1.caffè.test']);
        $ns->addresses()->create(['ip' => '192.0.2.1']);
        $ns->addresses()->create(['ip' => '2001:db8::1']);
        $domain->nameservers()->attach($ns);
        $domain->dsRecords()->create(['key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2, 'digest' => 'abcdef0123']);

        foreach (['caffè.test', 'xn--caff-8oa.test', 'XN--CAFF-8OA.TEST.'] as $query) {
            $json = $this->rdap($this->get('/rdap/domain/'.rawurlencode($query))->assertOk());

            $this->assertSame('domain', $json['objectClassName']);
            $this->assertSame('xn--caff-8oa.test', $json['ldhName']);
            $this->assertSame('caffè.test', $json['unicodeName']);
            $this->assertSame($domain->id.'-TEST', $json['handle']);
        }

        $this->assertSame(['rdap_level_0', 'redacted'], $json['rdapConformance']);
        $this->assertSame(['client transfer prohibited', 'server hold'], $json['status']);
        $this->assertEqualsCanonicalizing(
            ['registration', 'expiration', 'last changed', 'last update of RDAP database'],
            array_column($json['events'], 'eventAction'),
        );
        $this->assertSame('ns1.xn--caff-8oa.test', $json['nameservers'][0]['ldhName']);
        $this->assertSame('ns1.caffè.test', $json['nameservers'][0]['unicodeName']);
    }

    public function test_domain_lookup_exposes_nameservers_dnssec_and_notices(): void
    {
        $domain = $this->makeDomain('example.test');
        $ns = Nameserver::factory()->create(['name' => 'ns1.example.test']);
        $ns->addresses()->create(['ip' => '192.0.2.1']);
        $ns->addresses()->create(['ip' => '2001:db8::1']);
        $domain->nameservers()->attach($ns);
        $domain->dsRecords()->create(['key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2, 'digest' => 'abcdef0123']);

        $json = $this->rdap($this->get('/rdap/domain/example.test')->assertOk());

        $this->assertSame(['active'], $json['status']);
        $this->assertArrayNotHasKey('unicodeName', $json);
        $this->assertSame('ns1.example.test', $json['nameservers'][0]['ldhName']);
        $this->assertSame(['v4' => ['192.0.2.1'], 'v6' => ['2001:db8::1']], $json['nameservers'][0]['ipAddresses']);
        $this->assertTrue($json['secureDNS']['delegationSigned']);
        $this->assertSame([['keyTag' => 12345, 'algorithm' => 13, 'digestType' => 2, 'digest' => 'ABCDEF0123']], $json['secureDNS']['dsData']);
        $this->assertSame('self', $json['links'][0]['rel']);
        $this->assertStringEndsWith('/rdap/domain/example.test', $json['links'][0]['href']);
        $this->assertSame(
            ['Terms of Use', 'Status Codes', 'RDDS Inaccuracy Complaint Form'],
            array_column($json['notices'], 'title'),
        );
    }

    public function test_registrar_entity_is_present_with_iana_id_and_abuse_contact(): void
    {
        $registrar = Registrar::factory()->create(['iana_id' => 9999, 'abuse_email' => 'abuse@registrar.example']);
        $this->makeDomain('sponsored.test', ['registrar_id' => $registrar->id]);

        $json = $this->rdap($this->get('/rdap/domain/sponsored.test')->assertOk());
        $entity = $this->entityWithRole($json, 'registrar');

        $this->assertNotNull($entity);
        $this->assertSame($registrar->handle, $entity['handle']);
        $this->assertSame([['type' => 'IANA Registrar ID', 'identifier' => '9999']], $entity['publicIds']);
        $this->assertSame(['abuse'], $entity['entities'][0]['roles']);
        $this->assertContains(['email', [], 'text', 'abuse@registrar.example'], $entity['entities'][0]['vcardArray'][1]);
    }

    public function test_registrar_entity_is_omitted_when_domain_has_no_registrar(): void
    {
        $this->makeDomain('direct.test', ['registrar_id' => null]);

        $json = $this->rdap($this->get('/rdap/domain/direct.test')->assertOk());

        $this->assertNull($this->entityWithRole($json, 'registrar'));
        $this->assertNotNull($this->entityWithRole($json, 'registrant'));
    }

    public function test_undisclosed_contact_fields_are_redacted(): void
    {
        $registrant = Contact::factory()->create([
            'handle' => 'C-SECRET',
            'name' => 'Mario Rossi',
            'email' => 'mario@example.org',
            'fax' => '+39.0123456789',
            'disclose' => [],
        ]);
        $tech = Contact::factory()->organization()->create([
            'handle' => 'C-TECH',
            'organization' => 'Acme S.p.A.',
            'email' => 'tech@acme.example',
            'disclose' => ['name', 'organization', 'email'],
        ]);
        $domain = $this->makeDomain('private.test', ['registrant_id' => $registrant->id]);
        $domain->contacts()->attach($tech->id, ['role' => ContactRole::Tech->value]);
        $domain->contacts()->attach($tech->id, ['role' => ContactRole::Admin->value]);

        $response = $this->get('/rdap/domain/private.test')->assertOk();
        $json = $this->rdap($response);

        $this->assertStringNotContainsString('Mario Rossi', $response->getContent());
        $this->assertStringNotContainsString('mario@example.org', $response->getContent());
        $this->assertStringNotContainsString('0123456789', $response->getContent());

        $reg = $this->entityWithRole($json, 'registrant');
        $this->assertSame('C-SECRET', $reg['handle']);
        $this->assertSame(['version', 'fn', 'kind'], $this->vcardProperties($reg));
        $this->assertSame(['fn', [], 'text', ''], $reg['vcardArray'][1][1]);

        $tech = $this->entityWithRole($json, 'technical');
        $this->assertSame(['administrative', 'technical'], $tech['roles']);
        $this->assertContains(['org', [], 'text', 'Acme S.p.A.'], $tech['vcardArray'][1]);
        $this->assertContains(['email', [], 'text', 'tech@acme.example'], $tech['vcardArray'][1]);
        $this->assertNotContains('adr', $this->vcardProperties($tech));

        $redacted = collect($json['redacted'])->keyBy(fn (array $r) => $r['name']['type']);

        $this->assertSame('emptyValue', $redacted['Registrant Name']['method']);
        $this->assertSame("$.entities[?(@.handle=='C-SECRET')].vcardArray[1][?(@[0]=='fn')][3]", $redacted['Registrant Name']['postPath']);
        $this->assertSame('removal', $redacted['Registrant Email']['method']);
        $this->assertSame('jsonpath', $redacted['Registrant Email']['pathLang']);
        $this->assertSame("$.entities[?(@.handle=='C-SECRET')].vcardArray[1][?(@[0]=='email')]", $redacted['Registrant Email']['prePath']);
        $this->assertArrayHasKey('Registrant Address', $redacted);
        $this->assertArrayHasKey('Registrant Phone', $redacted);
        $this->assertArrayHasKey('Registrant Fax', $redacted);
        $this->assertArrayHasKey('Admin Address', $redacted);
        $this->assertArrayNotHasKey('Admin Email', $redacted);
        $this->assertArrayNotHasKey('Admin Name', $redacted);
        $this->assertNotEmpty($redacted['Registrant Email']['reason']['description']);
    }

    public function test_not_found_returns_rdap_error_object(): void
    {
        $json = $this->rdap($this->get('/rdap/domain/missing.test')->assertNotFound());

        $this->assertSame(404, $json['errorCode']);
        $this->assertSame('Not Found', $json['title']);
        $this->assertIsArray($json['description']);
        $this->assertContains('rdap_level_0', $json['rdapConformance']);

        $this->rdap($this->get('/rdap/nameserver/ns.missing.test')->assertNotFound());
        $this->rdap($this->get('/rdap/entity/NOPE')->assertNotFound());
    }

    public function test_invalid_names_return_bad_request(): void
    {
        $json = $this->rdap($this->get('/rdap/domain/'.rawurlencode('bad_name!.test'))->assertBadRequest());
        $this->assertSame(400, $json['errorCode']);

        $this->rdap($this->get('/rdap/nameserver/-bad-.test')->assertBadRequest());
        $this->rdap($this->get('/rdap/domains?name=a*b*')->assertBadRequest());
        $this->rdap($this->get('/rdap/domains?name=a%25')->assertBadRequest());
        $this->rdap($this->get('/rdap/domains')->assertBadRequest());
        $this->rdap($this->get('/rdap/nameservers?ip=not-an-ip')->assertBadRequest());
        $this->rdap($this->get('/rdap/unknown/thing')->assertBadRequest());
    }

    public function test_entity_search_is_not_implemented(): void
    {
        $json = $this->rdap($this->get('/rdap/entities?fn=Mario*')->assertStatus(501));

        $this->assertSame(501, $json['errorCode']);
    }

    public function test_domain_search_supports_trailing_wildcard_and_unicode(): void
    {
        $this->makeDomain('alpha.test');
        $this->makeDomain('alphabet.test');
        $this->makeDomain('beta.test');
        $this->makeDomain('caffè.test');

        $json = $this->rdap($this->get('/rdap/domains?name=alpha*')->assertOk());
        $this->assertSame(['alpha.test', 'alphabet.test'], array_column($json['domainSearchResults'], 'ldhName'));

        $json = $this->rdap($this->get('/rdap/domains?name=alpha*.test')->assertOk());
        $this->assertCount(2, $json['domainSearchResults']);

        $json = $this->rdap($this->get('/rdap/domains?name=alpha*.other')->assertOk());
        $this->assertSame([], $json['domainSearchResults']);

        $json = $this->rdap($this->get('/rdap/domains?name='.rawurlencode('caffè*'))->assertOk());
        $this->assertSame(['xn--caff-8oa.test'], array_column($json['domainSearchResults'], 'ldhName'));

        $json = $this->rdap($this->get('/rdap/domains?name='.rawurlencode('caffè.test'))->assertOk());
        $this->assertSame(['caffè.test'], array_column($json['domainSearchResults'], 'unicodeName'));

        // Search results never carry personal contact data.
        $this->assertNull($this->entityWithRole($json['domainSearchResults'][0], 'registrant'));
    }

    public function test_domain_search_is_truncated_at_the_limit(): void
    {
        config(['rdap.search_limit' => 2]);

        foreach (['a1', 'a2', 'a3'] as $label) {
            $this->makeDomain("{$label}.test");
        }

        $json = $this->rdap($this->get('/rdap/domains?name=a*')->assertOk());

        $this->assertCount(2, $json['domainSearchResults']);
        $this->assertContains('result set truncated due to excessive load', array_column($json['notices'], 'type'));
    }

    public function test_nameserver_lookup_and_search(): void
    {
        $ns = Nameserver::factory()->create(['name' => 'ns1.example.test']);
        $ns->addresses()->create(['ip' => '192.0.2.53']);
        $ns->addresses()->create(['ip' => '2001:db8::53']);
        Nameserver::factory()->create(['name' => 'ns2.example.test']);
        Nameserver::factory()->create(['name' => 'dns.other.example']);

        $json = $this->rdap($this->get('/rdap/nameserver/NS1.example.test')->assertOk());
        $this->assertSame('nameserver', $json['objectClassName']);
        $this->assertSame('ns1.example.test', $json['ldhName']);
        $this->assertSame(['v4' => ['192.0.2.53'], 'v6' => ['2001:db8::53']], $json['ipAddresses']);

        $json = $this->rdap($this->get('/rdap/nameservers?name=ns*')->assertOk());
        $this->assertSame(['ns1.example.test', 'ns2.example.test'], array_column($json['nameserverSearchResults'], 'ldhName'));

        $json = $this->rdap($this->get('/rdap/nameservers?ip=2001:0db8:0000::53')->assertOk());
        $this->assertSame(['ns1.example.test'], array_column($json['nameserverSearchResults'], 'ldhName'));
    }

    public function test_entity_lookup_for_contacts_and_registrars(): void
    {
        $contact = Contact::factory()->organization()->create(['handle' => 'C-ORG', 'organization' => 'Acme']);
        $registrar = Registrar::factory()->create(['handle' => 'REG-1', 'iana_id' => 4242]);

        $json = $this->rdap($this->get('/rdap/entity/C-ORG')->assertOk());
        $this->assertSame('entity', $json['objectClassName']);
        $this->assertSame('C-ORG', $json['handle']);
        $this->assertContains(['fn', [], 'text', $contact->name], $json['vcardArray'][1]);
        $this->assertContains('adr', $this->vcardProperties($json));
        $this->assertNotContains('email', $this->vcardProperties($json));
        $redacted = collect($json['redacted'])->keyBy(fn (array $r) => $r['name']['type']);
        $this->assertSame("$.vcardArray[1][?(@[0]=='email')]", $redacted['Contact Email']['prePath']);

        $json = $this->rdap($this->get('/rdap/entity/REG-1')->assertOk());
        $this->assertSame(['registrar'], $json['roles']);
        $this->assertSame('4242', $json['publicIds'][0]['identifier']);
        $this->assertArrayNotHasKey('redacted', $json);
    }

    public function test_help_and_no_session_cookie(): void
    {
        $response = $this->get('/rdap/help')->assertOk();
        $json = $this->rdap($response);

        $this->assertSame(['rdap_level_0', 'redacted'], $json['rdapConformance']);
        $this->assertNotEmpty($json['notices']);
        $response->assertCookieMissing(config('session.cookie'));
    }
}
