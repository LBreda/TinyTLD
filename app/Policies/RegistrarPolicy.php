<?php

namespace App\Policies;

use App\Models\Registrar;
use App\Models\User;

class RegistrarPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Registrar $registrar): bool
    {
        return $user->operatesFor($registrar->id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Operators may edit the data of their own registrar.
     */
    public function update(User $user, Registrar $registrar): bool
    {
        return $user->operatesFor($registrar->id);
    }

    public function delete(User $user, Registrar $registrar): bool
    {
        return false;
    }
}
