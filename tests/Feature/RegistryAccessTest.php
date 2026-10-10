<?php

namespace Tests\Feature;

use App\Enums\ContactRole;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\Tld;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistryAccessTest extends TestCase
{
    use RefreshDatabase;

    private Registrar $acme;

    private Registrar $other;

    private Domain $acmeDomain;

    private Domain $otherDomain;

    protected function setUp(): void
    {
        parent::setUp();

        $tld = Tld::factory()->create(['name' => 'test']);
        $this->acme = Registrar::factory()->create(['name' => 'Acme']);
        $this->other = Registrar::factory()->create(['name' => 'Other']);
        $this->acmeDomain = Domain::factory()->for($tld)->for($this->acme)->create(['name' => 'acme.test']);
        $this->otherDomain = Domain::factory()->for($tld)->for($this->other)->create(['name' => 'other.test']);
    }

    public function test_operator_only_lists_domains_of_their_registrar(): void
    {
        $this->actingAs(User::factory()->operatorOf($this->acme)->create());

        $this->get(route('domains.index'))
            ->assertOk()
            ->assertSee('acme.test')
            ->assertDontSee('other.test');
    }

    public function test_operator_can_view_own_domain_but_not_others(): void
    {
        $this->actingAs(User::factory()->operatorOf($this->acme)->create());

        $this->get(route('domains.show', $this->acmeDomain))->assertOk();
        $this->get(route('domains.show', $this->otherDomain))->assertForbidden();
    }

    public function test_operator_cannot_edit_or_create_domains(): void
    {
        $this->actingAs(User::factory()->operatorOf($this->acme)->create());

        $this->get(route('domains.edit', $this->acmeDomain))->assertForbidden();
        $this->get(route('domains.create'))->assertForbidden();
    }

    public function test_operator_sees_only_contacts_of_their_domains(): void
    {
        $tech = Contact::factory()->create();
        $this->acmeDomain->contacts()->attach($tech->id, ['role' => ContactRole::Tech->value]);

        $this->actingAs(User::factory()->operatorOf($this->acme)->create());

        $this->get(route('contacts.index'))
            ->assertOk()
            ->assertSee($this->acmeDomain->registrant->handle)
            ->assertSee($tech->handle)
            ->assertDontSee($this->otherDomain->registrant->handle);

        $this->get(route('contacts.show', $this->acmeDomain->registrant))->assertOk();
        $this->get(route('contacts.show', $tech))->assertOk();
        $this->get(route('contacts.show', $this->otherDomain->registrant))->assertForbidden();
        $this->get(route('contacts.edit', $this->acmeDomain->registrant))->assertForbidden();
    }

    public function test_operator_can_update_own_registrar_profile_only(): void
    {
        $operator = User::factory()->operatorOf($this->acme)->create();
        $this->actingAs($operator);

        $this->get(route('registrars.mine'))->assertRedirect(route('registrars.edit', $this->acme));
        $this->get(route('registrars.edit', $this->other))->assertForbidden();
        $this->get(route('registrars.index'))->assertForbidden();

        $originalHandle = $this->acme->handle;

        Livewire::test('pages::registrars.form', ['registrar' => $this->acme])
            ->set('name', 'Acme Renamed')
            ->set('abuse_email', 'abuse@acme.test')
            ->set('handle', 'HIJACKED')
            ->call('save')
            ->assertHasNoErrors();

        $this->acme->refresh();
        $this->assertSame('Acme Renamed', $this->acme->name);
        $this->assertSame('abuse@acme.test', $this->acme->abuse_email);
        $this->assertSame($originalHandle, $this->acme->handle);
    }

    public function test_operator_cannot_reach_admin_pages(): void
    {
        $this->actingAs(User::factory()->operatorOf($this->acme)->create());

        foreach (['tlds.index', 'nameservers.index', 'users.index', 'registrars.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_user_without_registrar_sees_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('domains.index'))->assertForbidden();
        $this->get(route('contacts.index'))->assertForbidden();
    }

    public function test_admin_sees_everything(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('domains.index'))->assertOk()->assertSee('acme.test')->assertSee('other.test');

        foreach (['tlds.index', 'nameservers.index', 'users.index', 'registrars.index', 'registrars.create', 'domains.create', 'contacts.create'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('domains.edit', $this->acmeDomain))->assertOk();
        $this->get(route('registrars.edit', $this->other))->assertOk();
    }

    public function test_admin_can_create_domain_without_registrar(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $registrant = Contact::factory()->create();

        Livewire::test('pages::domains.form')
            ->set('label', 'perché')
            ->set('tld_id', (string) $this->acmeDomain->tld_id)
            ->set('registrant', $registrant->handle)
            ->set('registrar_id', '')
            ->set('nameservers', "ns1.example.net\nns2.example.net")
            ->set('ds_records', '12345 13 2 '.str_repeat('ab', 32))
            ->call('save')
            ->assertHasNoErrors();

        $domain = Domain::where('name', 'xn--perch-fsa.test')->firstOrFail();

        $this->assertNull($domain->registrar_id);
        $this->assertSame('perché.test', $domain->unicode_name);
        $this->assertCount(2, $domain->nameservers);
        $this->assertSame(12345, $domain->dsRecords->first()->key_tag);
    }

    public function test_admin_can_assign_operator_to_registrar(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::users.form')
            ->set('name', 'Jane Operator')
            ->set('email', 'jane@acme.test')
            ->set('password', 'secret-password')
            ->set('registrar_id', (string) $this->acme->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame($this->acme->id, $user->registrar_id);
        $this->assertFalse($user->isAdmin());
        $this->assertCount(1, $this->acme->users);
    }

    public function test_deleting_registrar_keeps_domains_without_registrar(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::registrars.form', ['registrar' => $this->acme])->call('delete');

        $this->assertNull($this->acmeDomain->fresh()->registrar_id);
    }
}
