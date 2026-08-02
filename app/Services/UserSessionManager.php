<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserSessionManager
{
    public function invalidate(User $user): void
    {
        $user->forceFill(['remember_token' => null])->saveQuietly();

        if (config('session.driver') === 'database' && Schema::hasTable((string) config('session.table', 'sessions'))) {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
