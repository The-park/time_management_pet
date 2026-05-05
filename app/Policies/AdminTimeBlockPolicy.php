<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\TimeBlock;

class AdminTimeBlockPolicy
{
    public function view(Admin $admin, TimeBlock $timeBlock): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, TimeBlock $timeBlock): bool
    {
        return false;
    }

    public function delete(Admin $admin, TimeBlock $timeBlock): bool
    {
        return false;
    }
}
