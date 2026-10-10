<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->registrar_id !== null;
    }

    public function view(User $user, Contact $contact): bool
    {
        return Contact::query()->visibleTo($user)->whereKey($contact->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Contact $contact): bool
    {
        return false;
    }

    public function delete(User $user, Contact $contact): bool
    {
        return false;
    }
}
