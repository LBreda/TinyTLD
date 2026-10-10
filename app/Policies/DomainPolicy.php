<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;

class DomainPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->registrar_id !== null;
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->operatesFor($domain->registrar_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Domain $domain): bool
    {
        return false;
    }

    public function delete(User $user, Domain $domain): bool
    {
        return false;
    }
}
