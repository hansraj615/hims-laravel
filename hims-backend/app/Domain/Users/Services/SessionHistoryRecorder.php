<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Models\UserSessionHistory;
use App\Models\User;
use Illuminate\Http\Request;

class SessionHistoryRecorder
{
    public function recordLogin(Request $request, User $user, string $method): void
    {
        UserSessionHistory::create([
            'user_id' => $user->id,
            'session_id' => $request->session()->getId(),
            'login_method' => $method,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'logged_in_at' => now(),
        ]);
    }

    public function recordCurrentLogout(Request $request, User $user): void
    {
        $updated = UserSessionHistory::query()
            ->where('user_id', $user->id)
            ->where('session_id', $request->session()->getId())
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now()]);

        if ($updated === 0) {
            UserSessionHistory::query()
                ->where('user_id', $user->id)
                ->whereNull('logged_out_at')
                ->latest('id')
                ->limit(1)
                ->update(['logged_out_at' => now()]);
        }
    }

    public function recordAllLogout(User $user): void
    {
        UserSessionHistory::query()
            ->where('user_id', $user->id)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now()]);
    }
}
