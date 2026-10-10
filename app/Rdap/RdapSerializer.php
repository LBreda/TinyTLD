<?php

namespace App\Rdap;

use App\Enums\ContactKind;
use App\Enums\ContactRole;
use App\Enums\DomainStatus;
use App\Enums\RegistrarStatus;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\DsRecord;
use App\Models\Nameserver;
use App\Models\NameserverAddress;
use App\Models\Registrar;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * Builds RDAP JSON responses (RFC 9083) with RFC 9537 redaction.
 */
class RdapSerializer
{
    public const MEDIA_TYPE = 'application/rdap+json';

    /**
     * @var list<string>
     */
    public const CONFORMANCE = ['rdap_level_0', 'redacted'];

    /**
     * Relations to eager load before serialising a domain.
     *
     * @var list<string>
     */
    public const DOMAIN_RELATIONS = ['tld', 'registrant', 'registrar', 'contacts', 'nameservers.addresses', 'dsRecords'];

    /**
     * Full response for a domain lookup.
     *
     * @return array<string, mixed>
     */
    public function domainResponse(Domain $domain): array
    {
        $redactions = new Redactions;
        $object = $this->domain($domain, $redactions, withContacts: true);

        return $this->wrap($object, $this->domainNotices(), $redactions);
    }

    /**
     * Full response for a nameserver lookup.
     *
     * @return array<string, mixed>
     */
    public function nameserverResponse(Nameserver $nameserver): array
    {
        return $this->wrap($this->nameserver($nameserver, withEvents: true), $this->defaultNotices());
    }

    /**
     * Full response for a contact entity lookup.
     *
     * @return array<string, mixed>
     */
    public function contactResponse(Contact $contact): array
    {
        $redactions = new Redactions;
        $object = $this->contact($contact, [], $redactions, '$', withEvents: true);

        return $this->wrap($object, $this->defaultNotices(), $redactions);
    }

    /**
     * Full response for a registrar entity lookup.
     *
     * @return array<string, mixed>
     */
    public function registrarResponse(Registrar $registrar): array
    {
        return $this->wrap($this->registrar($registrar, withEvents: true), $this->defaultNotices());
    }

    /**
     * @param  iterable<Domain>  $domains
     * @return array<string, mixed>
     */
    public function domainSearchResponse(iterable $domains, bool $truncated): array
    {
        $results = [];
        foreach ($domains as $domain) {
            $results[] = $this->domain($domain, new Redactions, withContacts: false);
        }

        return $this->searchResponse('domainSearchResults', $results, $truncated);
    }

    /**
     * @param  iterable<Nameserver>  $nameservers
     * @return array<string, mixed>
     */
    public function nameserverSearchResponse(iterable $nameservers, bool $truncated): array
    {
        $results = [];
        foreach ($nameservers as $nameserver) {
            $results[] = $this->nameserver($nameserver, withEvents: true);
        }

        return $this->searchResponse('nameserverSearchResults', $results, $truncated);
    }

    /**
     * @return array<string, mixed>
     */
    public function helpResponse(): array
    {
        return [
            'rdapConformance' => self::CONFORMANCE,
            'notices' => [
                ...$this->defaultNotices(),
                [
                    'title' => 'Help',
                    'description' => [
                        'Supported lookups: domain/{name}, nameserver/{name}, entity/{handle}.',
                        'Supported searches: domains?name=, nameservers?name=, nameservers?ip=.',
                        'Names may be given in Unicode or A-label form; a single trailing "*" acts as a wildcard.',
                    ],
                    'links' => [$this->link('help')],
                ],
            ],
        ];
    }

    /**
     * RDAP error object (RFC 9083 section 6).
     *
     * @param  list<string>  $description
     * @return array<string, mixed>
     */
    public function error(int $code, string $title, array $description = []): array
    {
        return array_filter([
            'rdapConformance' => self::CONFORMANCE,
            'errorCode' => $code,
            'title' => $title,
            'description' => $description,
            'notices' => $this->defaultNotices(),
        ], fn ($value) => $value !== []);
    }

    /**
     * Domain object class.
     *
     * @return array<string, mixed>
     */
    public function domain(Domain $domain, Redactions $redactions, bool $withContacts): array
    {
        $domain->loadMissing(self::DOMAIN_RELATIONS);

        $object = [
            'objectClassName' => 'domain',
            'handle' => $this->domainHandle($domain),
            'ldhName' => $domain->name,
        ];

        if ($domain->unicode_name && $domain->unicode_name !== $domain->name) {
            $object['unicodeName'] = $domain->unicode_name;
        }

        $object['status'] = $this->domainStatus($domain);
        $object['links'] = [$this->link('domain/'.$domain->name)];

        if ($domain->registrar?->url) {
            $object['links'][] = [
                'value' => $this->url('domain/'.$domain->name),
                'rel' => 'related',
                'href' => $domain->registrar->url,
                'type' => 'text/html',
            ];
        }

        $object['events'] = $this->events([
            'registration' => $domain->registered_at,
            'expiration' => $domain->expires_at,
            'last changed' => $domain->updated_at,
            'last update of RDAP database' => now(),
        ]);

        $object['nameservers'] = $domain->nameservers
            ->sortBy('name')
            ->map(fn (Nameserver $ns) => $this->nameserver($ns, withEvents: false))
            ->values()
            ->all();

        $object['secureDNS'] = [
            'delegationSigned' => $domain->dsRecords->isNotEmpty(),
            'dsData' => $domain->dsRecords
                ->sortBy(['key_tag', 'algorithm', 'digest_type', 'digest'])
                ->map(fn (DsRecord $ds) => [
                    'keyTag' => $ds->key_tag,
                    'algorithm' => $ds->algorithm,
                    'digestType' => $ds->digest_type,
                    'digest' => strtoupper($ds->digest),
                ])
                ->values()
                ->all(),
        ];

        $entities = [];

        if ($withContacts) {
            foreach ($this->contactRoles($domain) as [$contact, $roles]) {
                $path = "$.entities[?(@.handle=='{$contact->handle}')]";
                $entities[] = $this->contact($contact, $roles, $redactions, $path, withEvents: false);
            }
        }

        if ($domain->registrar !== null) {
            $entities[] = $this->registrar($domain->registrar, withEvents: false);
        }

        if ($entities !== []) {
            $object['entities'] = $entities;
        }

        if ($port43 = config('rdap.port43')) {
            $object['port43'] = $port43;
        }

        return $object;
    }

    /**
     * Nameserver object class.
     *
     * @return array<string, mixed>
     */
    public function nameserver(Nameserver $nameserver, bool $withEvents): array
    {
        $nameserver->loadMissing('addresses');

        $object = [
            'objectClassName' => 'nameserver',
            'handle' => $nameserver->id.'-NS',
            'ldhName' => $nameserver->name,
        ];

        if ($nameserver->unicode_name && $nameserver->unicode_name !== $nameserver->name) {
            $object['unicodeName'] = $nameserver->unicode_name;
        }

        $addresses = $nameserver->addresses->sortBy('ip');
        $ipAddresses = array_filter([
            'v4' => $addresses->where('version', 4)->map(fn (NameserverAddress $a) => $a->ip)->values()->all(),
            'v6' => $addresses->where('version', 6)->map(fn (NameserverAddress $a) => $a->ip)->values()->all(),
        ]);

        if ($ipAddresses !== []) {
            $object['ipAddresses'] = $ipAddresses;
        }

        $object['status'] = ['active'];
        $object['links'] = [$this->link('nameserver/'.$nameserver->name)];

        if ($withEvents) {
            $object['events'] = $this->events([
                'registration' => $nameserver->created_at,
                'last changed' => $nameserver->updated_at,
                'last update of RDAP database' => now(),
            ]);

            if ($port43 = config('rdap.port43')) {
                $object['port43'] = $port43;
            }
        }

        return $object;
    }

    /**
     * Contact entity rendered as jCard, publishing only disclosed fields.
     *
     * @param  list<string>  $roles
     * @param  string  $path  JSONPath of this entity inside the response
     * @return array<string, mixed>
     */
    public function contact(Contact $contact, array $roles, Redactions $redactions, string $path, bool $withEvents): array
    {
        $label = $this->redactionLabel($roles);
        $card = [['version', new stdClass, 'text', '4.0']];

        if ($contact->discloses('name')) {
            $card[] = ['fn', new stdClass, 'text', $contact->name];
        } else {
            $card[] = ['fn', new stdClass, 'text', ''];
            $redactions->emptyValue("{$label} Name", "{$path}.vcardArray[1][?(@[0]=='fn')][3]");
        }

        $card[] = ['kind', new stdClass, 'text', $contact->kind === ContactKind::Organization ? 'org' : 'individual'];

        if ($contact->organization !== null && $contact->organization !== '') {
            if ($contact->discloses('organization')) {
                $card[] = ['org', new stdClass, 'text', $contact->organization];
            } else {
                $redactions->removal("{$label} Organization", "{$path}.vcardArray[1][?(@[0]=='org')]");
            }
        }

        if ($contact->discloses('address')) {
            $card[] = $this->address($contact->street, $contact->city, $contact->state_province, $contact->postal_code, $contact->country_code);
        } else {
            $redactions->removal("{$label} Address", "{$path}.vcardArray[1][?(@[0]=='adr')]");
        }

        if ($contact->discloses('email')) {
            $card[] = ['email', new stdClass, 'text', $contact->email];
        } else {
            $redactions->removal("{$label} Email", "{$path}.vcardArray[1][?(@[0]=='email')]");
        }

        if ($contact->phone !== null && $contact->phone !== '') {
            if ($contact->discloses('phone')) {
                $card[] = $this->tel('voice', $contact->phone);
            } else {
                $redactions->removal("{$label} Phone", "{$path}.vcardArray[1][?(@[1].type=='voice')]");
            }
        }

        if ($contact->fax !== null && $contact->fax !== '') {
            if ($contact->discloses('fax')) {
                $card[] = $this->tel('fax', $contact->fax);
            } else {
                $redactions->removal("{$label} Fax", "{$path}.vcardArray[1][?(@[1].type=='fax')]");
            }
        }

        $object = [
            'objectClassName' => 'entity',
            'handle' => $contact->handle,
        ];

        if ($roles !== []) {
            $object['roles'] = $roles;
        }

        $object['vcardArray'] = ['vcard', $card];
        $object['links'] = [$this->link('entity/'.rawurlencode($contact->handle))];

        if ($withEvents) {
            $object['events'] = $this->events([
                'registration' => $contact->created_at,
                'last changed' => $contact->updated_at,
                'last update of RDAP database' => now(),
            ]);
        }

        return $object;
    }

    /**
     * Registrar entity with its IANA ID and nested abuse contact.
     *
     * @return array<string, mixed>
     */
    public function registrar(Registrar $registrar, bool $withEvents): array
    {
        $card = [
            ['version', new stdClass, 'text', '4.0'],
            ['fn', new stdClass, 'text', $registrar->name],
            ['kind', new stdClass, 'text', 'org'],
        ];

        if ($registrar->organization) {
            $card[] = ['org', new stdClass, 'text', $registrar->organization];
        }

        if ($registrar->street || $registrar->city || $registrar->country_code) {
            $card[] = $this->address($registrar->street, $registrar->city, $registrar->state_province, $registrar->postal_code, $registrar->country_code);
        }

        $card[] = ['email', new stdClass, 'text', $registrar->email];

        if ($registrar->phone) {
            $card[] = $this->tel('voice', $registrar->phone);
        }

        if ($registrar->url) {
            $card[] = ['url', new stdClass, 'uri', $registrar->url];
        }

        $object = [
            'objectClassName' => 'entity',
            'handle' => $registrar->handle,
            'roles' => ['registrar'],
        ];

        if ($registrar->iana_id !== null) {
            $object['publicIds'] = [['type' => 'IANA Registrar ID', 'identifier' => (string) $registrar->iana_id]];
        }

        $object['vcardArray'] = ['vcard', $card];
        $object['status'] = [$registrar->status === RegistrarStatus::Active ? 'active' : 'inactive'];
        $object['links'] = [$this->link('entity/'.rawurlencode($registrar->handle))];

        if ($registrar->abuse_email || $registrar->abuse_phone) {
            $abuse = [
                ['version', new stdClass, 'text', '4.0'],
                ['fn', new stdClass, 'text', 'Abuse contact of '.$registrar->name],
                ['kind', new stdClass, 'text', 'group'],
            ];

            if ($registrar->abuse_email) {
                $abuse[] = ['email', new stdClass, 'text', $registrar->abuse_email];
            }

            if ($registrar->abuse_phone) {
                $abuse[] = $this->tel('voice', $registrar->abuse_phone);
            }

            $object['entities'] = [[
                'objectClassName' => 'entity',
                'roles' => ['abuse'],
                'vcardArray' => ['vcard', $abuse],
            ]];
        }

        if ($withEvents) {
            $object['events'] = $this->events([
                'registration' => $registrar->created_at,
                'last changed' => $registrar->updated_at,
                'last update of RDAP database' => now(),
            ]);
        }

        return $object;
    }

    public function domainHandle(Domain $domain): string
    {
        return $domain->id.'-'.strtoupper($domain->tld->name);
    }

    /**
     * @return list<string>
     */
    public function domainStatus(Domain $domain): array
    {
        $statuses = array_unique(array_map(
            fn (DomainStatus $status) => $status->rdap(),
            ($domain->statuses ?? collect())->all(),
        ));
        sort($statuses);

        return $statuses === [] ? ['active'] : $statuses;
    }

    /**
     * Contacts of the domain grouped with all their RDAP roles.
     *
     * @return list<array{0: Contact, 1: list<string>}>
     */
    private function contactRoles(Domain $domain): array
    {
        /** @var array<int, array{0: Contact, 1: list<string>}> $grouped */
        $grouped = [];

        $grouped[$domain->registrant->id] = [$domain->registrant, ['registrant']];

        foreach (ContactRole::cases() as $role) {
            foreach ($domain->contacts->sortBy('handle') as $contact) {
                if ($contact->pivot?->role !== $role->value) {
                    continue;
                }

                $grouped[$contact->id] ??= [$contact, []];

                if (! in_array($role->rdap(), $grouped[$contact->id][1], true)) {
                    $grouped[$contact->id][1][] = $role->rdap();
                }
            }
        }

        return array_values($grouped);
    }

    /**
     * @param  list<string>  $roles
     */
    private function redactionLabel(array $roles): string
    {
        return match ($roles[0] ?? null) {
            'registrant' => 'Registrant',
            'administrative' => 'Admin',
            'technical' => 'Tech',
            'billing' => 'Billing',
            default => 'Contact',
        };
    }

    /**
     * @return list<mixed>
     */
    private function address(?string $street, ?string $city, ?string $region, ?string $postalCode, ?string $countryCode): array
    {
        $parameters = $countryCode ? ['cc' => strtoupper($countryCode)] : new stdClass;

        return ['adr', $parameters, 'text', ['', '', (string) $street, (string) $city, (string) $region, (string) $postalCode, '']];
    }

    /**
     * @return list<mixed>
     */
    private function tel(string $type, string $number): array
    {
        return ['tel', ['type' => $type], 'uri', 'tel:'.$number];
    }

    /**
     * @param  array<string, DateTimeInterface|null>  $events
     * @return list<array{eventAction: string, eventDate: string}>
     */
    private function events(array $events): array
    {
        $result = [];

        foreach ($events as $action => $date) {
            if ($date !== null) {
                $result[] = [
                    'eventAction' => $action,
                    'eventDate' => Carbon::instance($date)->utc()->format('Y-m-d\TH:i:s\Z'),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  list<array<string, mixed>>  $notices
     * @return array<string, mixed>
     */
    private function wrap(array $object, array $notices, ?Redactions $redactions = null): array
    {
        $response = ['rdapConformance' => self::CONFORMANCE, ...$object, 'notices' => $notices];

        if ($redactions !== null && ! $redactions->isEmpty()) {
            $response['redacted'] = $redactions->toArray();
        }

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function searchResponse(string $member, array $results, bool $truncated): array
    {
        $notices = $this->defaultNotices();

        if ($truncated) {
            $notices[] = [
                'title' => 'Search query limits',
                'type' => 'result set truncated due to excessive load',
                'description' => ['Only the first '.config('rdap.search_limit').' results are returned.'],
            ];
        }

        return [
            'rdapConformance' => self::CONFORMANCE,
            $member => $results,
            'notices' => $notices,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultNotices(): array
    {
        return [[
            'title' => 'Terms of Use',
            'description' => array_values((array) config('rdap.terms_of_service')),
            'links' => [[
                'value' => $this->url('help'),
                'rel' => 'terms-of-service',
                'href' => (string) config('rdap.terms_of_service_url'),
                'type' => 'text/html',
            ]],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function domainNotices(): array
    {
        return [
            ...$this->defaultNotices(),
            [
                'title' => 'Status Codes',
                'description' => ['For more information on domain status codes, please visit https://icann.org/epp'],
                'links' => [[
                    'value' => $this->url('help'),
                    'rel' => 'glossary',
                    'href' => (string) config('rdap.status_codes_url'),
                    'type' => 'text/html',
                ]],
            ],
            [
                'title' => 'RDDS Inaccuracy Complaint Form',
                'description' => ['URL of the ICANN RDDS Inaccuracy Complaint Form: https://icann.org/wicf'],
                'links' => [[
                    'value' => $this->url('help'),
                    'rel' => 'help',
                    'href' => (string) config('rdap.inaccuracy_complaint_url'),
                    'type' => 'text/html',
                ]],
            ],
        ];
    }

    /**
     * @return array{value: string, rel: string, href: string, type: string}
     */
    private function link(string $path): array
    {
        $url = $this->url($path);

        return ['value' => $url, 'rel' => 'self', 'href' => $url, 'type' => self::MEDIA_TYPE];
    }

    private function url(string $path): string
    {
        return config('rdap.base_url').'/'.$path;
    }
}
