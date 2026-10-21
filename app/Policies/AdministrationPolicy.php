<?php

namespace App\Policies;

use App\Models\User;

class AdministrationPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
