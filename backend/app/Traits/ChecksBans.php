<?php

namespace App\Traits;

use App\Models\BannedUser;
use Illuminate\Http\JsonResponse;

trait ChecksBans
{
    /**
     * Check if the authenticated user is banned.
     * Returns JsonResponse if banned, null otherwise.
     */
    protected function checkUserBan($user): ?JsonResponse
    {
        if (!$user) {
            return null;
        }

        $bannedUser = BannedUser::where('user_id', $user->id)->active()->first();
        
        if ($bannedUser) {
            $message = 'Your account has been banned';
            if ($bannedUser->reason) {
                $message .= ': ' . $bannedUser->reason;
            }
            if ($bannedUser->ends_at && !$bannedUser->is_permanent) {
                $message .= ' (Ban expires: ' . $bannedUser->ends_at->format('Y-m-d H:i:s') . ')';
            }
            
            return response()->json([
                'message' => $message,
                'banned' => true,
                'ban_reason' => $bannedUser->reason,
                'is_permanent' => $bannedUser->is_permanent,
                'ends_at' => $bannedUser->ends_at?->toISOString(),
            ], 403);
        }

        return null;
    }
}



