<?php

use App\Enums\ContactRole;
use App\Enums\DomainStatus;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Nameserver;
use App\Models\Registrar;
use App\Models\Tld;
use App\Support\Idn;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Domain')] class extends Component {
    public ?Domain $domain = null;

    public string $label = '';
    public string $tld_id = '';
    public string $registrant = '';
    public string $registrar_id = '';
    /** @var list<string> */
    public array $statuses = [];
    public string $auth_code = '';
    public string $registered_at = '';
    public string $expires_at = '';
    public string $admin_contacts = '';
    public string $tech_contacts = '';
    public string $billing_contacts = '';
    public string $nameservers = '';
    public string $ds_records = '';

    public function mount(?Domain $domain = null): void
    {
        if ($domain?->exists) {
            $this->authorize('update', $domain);

            $domain->load(['tld', 'registrant', 'contacts', 'nameservers', 'dsRecords']);

            $this->domain = $domain;
            $this->tld_id = (string) $domain->tld_id;
            $this->label = Idn::toUnicode(Str::beforeLast($domain->name, '.'.$domain->tld->name));
            $this->registrant = $domain->registrant->handle;
            $this->registrar_id = (string) $domain->registrar_id;
            $this->statuses = ($domain->statuses ?? collect())->map(fn (DomainStatus $s) => $s->value)->values()->all();
            $this->auth_code = (string) $domain->auth_code;
            $this->registered_at = (string) $domain->registered_at?->toDateString();
            $this->expires_at = (string) $domain->expires_at?->toDateString();
            $this->admin_contacts = $this->handlesFor($domain, ContactRole::Admin);
            $this->tech_contacts = $this->handlesFor($domain, ContactRole::Tech);
            $this->billing_contacts = $this->handlesFor($domain, ContactRole::Billing);
            $this->nameservers = $domain->nameservers->map->displayName()->join("\n");
            $this->ds_records = $domain->dsRecords
                ->map(fn ($ds) => "{$ds->key_tag} {$ds->algorithm} {$ds->digest_type} {$ds->digest}")
                ->join("\n");
        } else {
            $this->authorize('create', Domain::class);

            $this->tld_id = (string) Tld::query()->orderBy('name')->value('id');
            $this->auth_code = Str::password(16, symbols: false);
            $this->registered_at = now()->toDateString();
            $this->expires_at = now()->addYear()->toDateString();
        }
    }

    #[Computed]
    public function tlds(): Collection
    {
        return Tld::query()->orderBy('name')->get();
    }

    #[Computed]
    public function registrars(): Collection
    {
        return Registrar::query()->orderBy('name')->get(['id', 'name', 'handle']);
    }

    public function save(): void
    {
        $this->authorize($this->domain ? 'update' : 'create', $this->domain ?? Domain::class);

        $this->validate([
            'label' => ['required', 'string', 'max:63'],
            'tld_id' => ['required', 'exists:tlds,id'],
            'registrant' => ['required', 'exists:contacts,handle'],
            'registrar_id' => ['nullable', 'exists:registrars,id'],
            'statuses' => ['array'],
            'statuses.*' => [Rule::enum(DomainStatus::class)],
            'auth_code' => ['nullable', 'string', 'max:255'],
            'registered_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:registered_at'],
        ]);

        $tld = Tld::findOrFail($this->tld_id);
        $fqdn = trim($this->label, '. ').'.'.$tld->name;

        if (! Idn::isValidHostName($fqdn) || str_contains(Idn::toAscii($this->label), '.')) {
            throw ValidationException::withMessages(['label' => __('Invalid domain label.')]);
        }

        $name = Idn::toAscii($fqdn);

        if (Domain::query()->where('name', $name)->when($this->domain, fn ($q) => $q->whereKeyNot($this->domain->id))->exists()) {
            throw ValidationException::withMessages(['label' => __('This domain is already registered.')]);
        }

        $roles = [
            ContactRole::Admin->value => $this->parseHandles('admin_contacts'),
            ContactRole::Tech->value => $this->parseHandles('tech_contacts'),
            ContactRole::Billing->value => $this->parseHandles('billing_contacts'),
        ];
        $hosts = $this->parseNameservers();
        $dsRecords = $this->parseDsRecords();

        $domain = DB::transaction(function () use ($tld, $name, $roles, $hosts, $dsRecords) {
            $domain = $this->domain ?? new Domain;

            $domain->fill([
                'tld_id' => $tld->id,
                'name' => $name,
                'registrant_id' => Contact::where('handle', $this->registrant)->value('id'),
                'registrar_id' => $this->registrar_id ?: null,
                'statuses' => $this->statuses,
                'auth_code' => $this->auth_code ?: null,
                'registered_at' => $this->registered_at ?: null,
                'expires_at' => $this->expires_at ?: null,
            ])->save();

            $domain->contacts()->detach();
            foreach ($roles as $role => $ids) {
                foreach ($ids as $id) {
                    $domain->contacts()->attach($id, ['role' => $role]);
                }
            }

            $domain->nameservers()->sync(
                collect($hosts)->map(fn (string $host) => Nameserver::firstOrCreate(['name' => $host])->id)->all()
            );

            $domain->dsRecords()->delete();
            $domain->dsRecords()->createMany($dsRecords);

            return $domain;
        });

        Flux::toast(variant: 'success', text: __('Domain saved.'));

        $this->redirectRoute('domains.show', $domain, navigate: true);
    }

    private function handlesFor(Domain $domain, ContactRole $role): string
    {
        return $domain->contacts->where('pivot.role', $role->value)->pluck('handle')->join(', ');
    }

    /**
     * @return list<int>
     */
    private function parseHandles(string $field): array
    {
        $handles = collect(preg_split('/[\s,]+/', $this->{$field}, -1, PREG_SPLIT_NO_EMPTY))->unique()->values();
        $ids = Contact::query()->whereIn('handle', $handles)->pluck('id', 'handle');

        if ($missing = $handles->diff($ids->keys())->join(', ')) {
            throw ValidationException::withMessages([$field => __('Unknown contacts: :handles', ['handles' => $missing])]);
        }

        return $ids->values()->all();
    }

    /**
     * @return list<string>
     */
    private function parseNameservers(): array
    {
        $hosts = [];

        foreach (preg_split('/[\s,]+/', $this->nameservers, -1, PREG_SPLIT_NO_EMPTY) as $host) {
            if (! Idn::isValidHostName($host) || ! str_contains($host, '.')) {
                throw ValidationException::withMessages(['nameservers' => __('Invalid host name: :host', ['host' => $host])]);
            }

            $hosts[] = Idn::toAscii($host);
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return list<array{key_tag: int, algorithm: int, digest_type: int, digest: string}>
     */
    private function parseDsRecords(): array
    {
        $records = [];

        foreach (preg_split('/\R/', trim($this->ds_records), -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $parts = preg_split('/\s+/', trim($line), 4);

            if (count($parts) !== 4 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])
                || (int) $parts[0] > 65535 || (int) $parts[1] > 255 || (int) $parts[2] > 255
                || ! preg_match('/^[0-9a-fA-F\s]+$/', $parts[3])) {
                throw ValidationException::withMessages(['ds_records' => __('Invalid DS record: :line', ['line' => $line])]);
            }

            $records[] = [
                'key_tag' => (int) $parts[0],
                'algorithm' => (int) $parts[1],
                'digest_type' => (int) $parts[2],
                'digest' => strtoupper(preg_replace('/\s+/', '', $parts[3])),
            ];
        }

        return $records;
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <flux:heading size="xl" level="1">{{ $domain ? __('Edit :name', ['name' => $domain->displayName()]) : __('New domain') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input.group :label="__('Domain')" :description="__('Unicode labels are converted to Punycode automatically.')">
            <flux:input wire:model="label" :placeholder="__('Name')" required />
            <flux:select wire:model="tld_id" class="w-auto shrink-0 min-w-36">
                @foreach ($this->tlds as $tld)
                    <flux:select.option :value="$tld->id">.{{ $tld->displayName() }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:input.group>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="registrant" :label="__('Registrant handle')" required />
            <flux:select wire:model="registrar_id" :label="__('Registrar')">
                <flux:select.option value="">{{ __('None (managed by the registry)') }}</flux:select.option>
                @foreach ($this->registrars as $registrar)
                    <flux:select.option :value="$registrar->id">{{ $registrar->name }} ({{ $registrar->handle }})</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="admin_contacts" :label="__('Admin contacts')" :placeholder="__('Handles, comma separated')" />
            <flux:input wire:model="tech_contacts" :label="__('Tech contacts')" :placeholder="__('Handles, comma separated')" />
            <flux:input wire:model="billing_contacts" :label="__('Billing contacts')" :placeholder="__('Handles, comma separated')" />
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:input wire:model="registered_at" type="date" :label="__('Registered')" />
            <flux:input wire:model="expires_at" type="date" :label="__('Expires')" />
            <flux:input wire:model="auth_code" :label="__('Auth code')" />
        </div>

        <flux:checkbox.group wire:model="statuses" :label="__('Statuses')" class="grid gap-2 md:grid-cols-3">
            @foreach (App\Enums\DomainStatus::cases() as $status)
                <flux:checkbox :value="$status->value" :label="$status->value" />
            @endforeach
        </flux:checkbox.group>

        <flux:textarea wire:model="nameservers" :label="__('Nameservers')" :description="__('One host name per line. Unknown hosts are created.')" rows="3" />

        <flux:textarea wire:model="ds_records" :label="__('DS records')" :description="__('One per line: key tag, algorithm, digest type, digest.')" rows="3" class="font-mono" />

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button :href="$domain ? route('domains.show', $domain) : route('domains.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
