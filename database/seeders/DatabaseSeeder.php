<?php

namespace Database\Seeders;

use App\Enums\ContactRole;
use App\Enums\DomainStatus;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Nameserver;
use App\Models\Registrar;
use App\Models\Tld;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a small demo registry.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'admin@example.com')->doesntExist()) {
            User::factory()->admin()->create([
                'name' => 'Registry Admin',
                'email' => 'admin@example.com',
            ]);
        }

        $tld = Tld::factory()->create([
            'name' => 'example',
            'soa_mname' => 'a.nic.example',
            'soa_rname' => 'hostmaster.nic.example',
            'apex_nameservers' => ['a.nic.example', 'b.nic.example'],
        ]);

        $registrars = Registrar::factory()->count(2)->sequence(
            ['handle' => 'ACME-REG', 'name' => 'Acme Domains'],
            ['handle' => 'NET-REG', 'name' => 'Net Registrar'],
        )->create();

        foreach ($registrars as $i => $registrar) {
            User::factory()->operatorOf($registrar)->create([
                'name' => "{$registrar->name} Operator",
                'email' => 'operator'.($i + 1).'@example.com',
            ]);
        }

        $tech = Contact::factory()->organization()->create(['handle' => 'TECH-1']);
        $ns1 = Nameserver::create(['name' => 'ns1.dns-provider.net']);
        $ns2 = Nameserver::create(['name' => 'ns.acme.example']);
        $ns2->addresses()->createMany([['ip' => '192.0.2.53'], ['ip' => '2001:db8::53']]);

        foreach ($registrars as $registrar) {
            Domain::factory()->count(3)->for($tld)->for($registrar)->create()
                ->each(function (Domain $domain) use ($tech, $ns1) {
                    $domain->contacts()->attach($tech->id, ['role' => ContactRole::Tech->value]);
                    $domain->nameservers()->attach($ns1->id);
                });
        }

        $acme = Domain::factory()->for($tld)->for($registrars[0])->create(['name' => 'acme.example']);
        $acme->nameservers()->attach([$ns1->id, $ns2->id]);
        $acme->dsRecords()->create(['key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2, 'digest' => str_repeat('AB', 32)]);

        Domain::factory()->for($tld)->withoutRegistrar()->create([
            'name' => 'nic.example',
            'statuses' => [DomainStatus::ServerDeleteProhibited, DomainStatus::ServerTransferProhibited],
        ]);

        Domain::factory()->for($tld)->for($registrars[1])->create([
            'name' => 'città.example',
            'statuses' => [DomainStatus::ClientHold],
        ]);
    }
}
