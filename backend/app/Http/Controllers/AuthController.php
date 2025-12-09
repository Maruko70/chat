<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RoleGroup;
use App\Models\LoginLog;
use App\Models\BannedBrowser;
use App\Models\BannedOperatingSystem;
use App\Models\BannedUser;
use App\Services\IpGeolocationService;
use App\Services\UserAgentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $userAgentService;

    public function __construct()
    {
        $this->userAgentService = new UserAgentService();
    }

    /**
     * Check if browser/OS is banned.
     */
    protected function checkBrowserAndOSBan(Request $request): ?JsonResponse
    {
        $userAgent = $request->userAgent();
        $deviceInfo = $this->userAgentService->getDeviceInfo($userAgent);

        // Check browser ban
        if ($deviceInfo['browser']) {
            $bannedBrowser = BannedBrowser::where('browser_name', $deviceInfo['browser'])->first();
            if ($bannedBrowser) {
                return response()->json([
                    'message' => 'Request rejected.',
                ], 403);
            }
        }

        // Check OS ban
        if ($deviceInfo['os']) {
            $bannedOS = BannedOperatingSystem::where('os_name', $deviceInfo['os'])->first();
            if ($bannedOS) {
                return response()->json([
                    'message' => 'Request rejected.',
                ], 403);
            }
        }

        return null;
    }

    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        // Check browser/OS ban
        $banCheck = $this->checkBrowserAndOSBan($request);
        if ($banCheck) {
            return $banCheck;
        }

        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:1',
        ]);

        // Check if username is already taken by a non-guest user
        $existingUser = User::where('username', $validated['username'])
            ->where('is_guest', false)
            ->first();
        
        if ($existingUser) {
            throw ValidationException::withMessages([
                'username' => ['The username has already been taken.'],
            ]);
        }

        // Get IP address and country data
        $ipService = new IpGeolocationService();
        $ipAddress = $ipService->getClientIp($request);
        $countryData = $ipService->getCountryDataFromIp($ipAddress);
        $country = $countryData['country'];
        $countryCode = $countryData['countryCode'] ?? 'SA'; // Default to SA
        $countryFlag = $ipService->countryCodeToFlag($countryCode); // Will always return a flag now

        // Check if a guest account exists with this username
        $guestUser = User::where('username', $validated['username'])
            ->where('is_guest', true)
            ->first();

        if ($guestUser) {
            // Check if guest user is banned
            $bannedUser = BannedUser::where('user_id', $guestUser->id)->active()->first();
            if ($bannedUser) {
                throw ValidationException::withMessages([
                    'username' => ['Request rejected.'],
                ]);
            }

            // Convert guest account to regular account
            $guestUser->update([
                'password' => Hash::make($validated['password']),
                'is_guest' => false,
                'ip_address' => $ipAddress,
                'country' => $country,
                'country_code' => $countryCode,
                'country_flag' => $countryFlag,
            ]);

            $user = $guestUser;

            // Ensure default role group is assigned if guest doesn't have any role groups
            if ($user->roleGroups()->count() === 0) {
                $defaultRoleGroup = RoleGroup::where('priority', 0)
                    ->where('is_active', true)
                    ->first();
                
                if ($defaultRoleGroup) {
                    $user->roleGroups()->attach($defaultRoleGroup->id);
                }
            }
        } else {
            // Create new user account
            $user = User::create([
                'username' => $validated['username'],
                'name' => $validated['username'], // Use username as name initially
                'password' => Hash::make($validated['password']),
                'is_guest' => false,
                'ip_address' => $ipAddress,
                'country' => $country,
                'country_code' => $countryCode,
                'country_flag' => $countryFlag,
            ]);

            // Assign default role group (priority 0) to new user
            $defaultRoleGroup = RoleGroup::where('priority', 0)
                ->where('is_active', true)
                ->first();
            
            if ($defaultRoleGroup) {
                $user->roleGroups()->attach($defaultRoleGroup->id);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Load media relationship for avatar_url and role groups
        $user->load('media', 'roleGroups');

        return response()->json([
            'user' => $user,
            'token' => $token,
            'last_edit_time' => $user->updated_at->toISOString(),
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        // Check browser/OS ban
        $banCheck = $this->checkBrowserAndOSBan($request);
        if ($banCheck) {
            return $banCheck;
        }

        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)
            ->where('is_guest', false)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is banned
        $bannedUser = BannedUser::where('user_id', $user->id)->active()->first();
        if ($bannedUser) {
            throw ValidationException::withMessages([
                'username' => ['Request rejected.'],
            ]);
        }

        // Update IP address and country on login
        $ipService = new IpGeolocationService();
        $ipAddress = $ipService->getClientIp($request);
        $countryData = $ipService->getCountryDataFromIp($ipAddress);
        $country = $countryData['country'];
        $countryCode = $countryData['countryCode'];
        $countryFlag = $countryCode ? $ipService->countryCodeToFlag($countryCode) : null;

        $user->update([
            'ip_address' => $ipAddress,
            'country' => $country,
            'country_code' => $countryCode,
            'country_flag' => $countryFlag,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Log login event
        LoginLog::create([
            'user_id' => $user->id,
            'is_guest' => false,
            'username' => $user->username,
            'ip_address' => $ipAddress,
            'country' => $country,
            'user_agent' => $request->userAgent(),
        ]);
        
        // Load media relationship for avatar_url and role groups
        $user->load('media', 'roleGroups');

        return response()->json([
            'user' => $user,
            'token' => $token,
            'last_edit_time' => $user->updated_at->toISOString(),
        ]);
    }

    /**
     * Guest login - create or retrieve guest user.
     */
    public function guestLogin(Request $request): JsonResponse
    {
        // Check browser/OS ban
        $banCheck = $this->checkBrowserAndOSBan($request);
        if ($banCheck) {
            return $banCheck;
        }

        $validated = $request->validate([
            'username' => 'required|string|max:255',
        ]);

        // Get IP address and country data
        $ipService = new IpGeolocationService();
        $ipAddress = $ipService->getClientIp($request);
        $countryData = $ipService->getCountryDataFromIp($ipAddress);
        $country = $countryData['country'];
        $countryCode = $countryData['countryCode'] ?? 'SD'; // Default to SA
        $countryFlag = $ipService->countryCodeToFlag($countryCode); // Will always return a flag now

        // Check if guest user already exists with this username
        $user = User::where('username', $validated['username'])
            ->where('is_guest', true)
            ->first();

        // Check if user is banned (if exists)
        if ($user) {
            $bannedUser = BannedUser::where('user_id', $user->id)->active()->first();
            if ($bannedUser) {
                throw ValidationException::withMessages([
                    'username' => ['Request rejected.'],
                ]);
            }
        }

        if (!$user) {
            // Create new guest user
            $user = User::create([
                'username' => $validated['username'],
                'name' => $validated['username'],
                'password' => Hash::make(Str::random(32)), // Random password for guests
                'is_guest' => true,
                'ip_address' => $ipAddress,
                'country' => $country,
                'country_code' => $countryCode,
                'country_flag' => $countryFlag,
            ]);

            // Assign default role group (priority 0) to new guest user
            $defaultRoleGroup = RoleGroup::where('priority', 0)
                ->where('is_active', true)
                ->first();
            
            if ($defaultRoleGroup) {
                $user->roleGroups()->attach($defaultRoleGroup->id);
            }
        } else {
            // Update IP address and country for existing guest
            $user->update([
                'ip_address' => $ipAddress,
                'country' => $country,
                'country_flag' => $countryFlag,
            ]);

            // Assign default role group (priority 0) if guest doesn't have any role groups
            if ($user->roleGroups()->count() === 0) {
                $defaultRoleGroup = RoleGroup::where('priority', 0)
                    ->where('is_active', true)
                    ->first();
                
                if ($defaultRoleGroup) {
                    $user->roleGroups()->attach($defaultRoleGroup->id);
                }
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Log login event
        LoginLog::create([
            'user_id' => $user->id,
            'is_guest' => true,
            'username' => $user->username,
            'ip_address' => $ipAddress,
            'country' => $country,
            'user_agent' => $request->userAgent(),
        ]);
        
        // Load media relationship for avatar_url and role groups
        $user->load('media', 'roleGroups');

        return response()->json([
            'user' => $user,
            'token' => $token,
            'last_edit_time' => $user->updated_at->toISOString(),
        ]);
    }

    /**
     * Validate credentials without logging (for fast login check).
     */
    public function validateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)
            ->where('is_guest', false)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'valid' => false,
            ], 401);
        }

        // Check if user is banned
        $bannedUser = BannedUser::where('user_id', $user->id)->active()->first();
        if ($bannedUser) {
            return response()->json([
                'valid' => false,
                'banned' => true,
            ], 403);
        }

        return response()->json([
            'valid' => true,
            'last_edit_time' => $user->updated_at->toISOString(),
        ]);
    }

    /**
     * Background authentication - refresh token and check user status.
     */
    public function backgroundAuth(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)
            ->where('is_guest', false)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check if user is banned
        $bannedUser = BannedUser::where('user_id', $user->id)->active()->first();
        if ($bannedUser) {
            // Revoke all tokens
            $user->tokens()->delete();
            
            $message = 'Your account has been banned';
            if ($bannedUser->reason) {
                $message .= ': ' . $bannedUser->reason;
            }
            if ($bannedUser->ends_at && !$bannedUser->is_permanent) {
                $message .= ' (Ban expires: ' . $bannedUser->ends_at->format('Y-m-d H:i:s') . ')';
            }
            
            return response()->json([
                'valid' => false,
                'banned' => true,
                'message' => $message,
                'ban_reason' => $bannedUser->reason,
                'is_permanent' => $bannedUser->is_permanent,
                'ends_at' => $bannedUser->ends_at?->toISOString(),
            ], 403);
        }

        // Update IP address and country
        $ipService = new IpGeolocationService();
        $ipAddress = $ipService->getClientIp($request);
        $countryData = $ipService->getCountryDataFromIp($ipAddress);
        $country = $countryData['country'];
        $countryCode = $countryData['countryCode'];
        $countryFlag = $countryCode ? $ipService->countryCodeToFlag($countryCode) : null;

        $user->update([
            'ip_address' => $ipAddress,
            'country' => $country,
            'country_code' => $countryCode,
            'country_flag' => $countryFlag,
        ]);

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Load media relationship for avatar_url and role groups
        $user->load('media', 'roleGroups');

        return response()->json([
            'valid' => true,
            'user' => $user,
            'token' => $token,
            'last_edit_time' => $user->updated_at->toISOString(),
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is banned
        $bannedUser = \App\Models\BannedUser::where('user_id', $user->id)->active()->first();
        if ($bannedUser) {
            // Revoke all tokens to force logout
            $user->tokens()->delete();
            
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
        
        $user->load('media', 'roleGroups');
        return response()->json($user);
    }
}

