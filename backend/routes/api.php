<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\WallPostController;
use App\Http\Controllers\YouTubeController;
use App\Http\Controllers\RoleGroupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SymbolController;
use App\Http\Controllers\SiteSettingsController;
use App\Http\Controllers\ScheduledMessageController;
use App\Http\Controllers\MembershipDesignController;
use App\Http\Controllers\ShortcutsController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\BanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\FilteredWordController;
use App\Http\Controllers\FilteredWordViolationController;
use App\Http\Controllers\UserWarningController;
use App\Http\Controllers\BootstrapController;
use App\Http\Controllers\UserStatusController;
use App\Http\Controllers\StoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/guest-login', [AuthController::class, 'guestLogin']);
Route::post('/validate-credentials', [AuthController::class, 'validateCredentials']);
Route::post('/background-auth', [AuthController::class, 'backgroundAuth']);

// Public room routes (for home page)
Route::get('/chat', [RoomController::class, 'index']);
Route::get('/users/active', [RoomController::class, 'getActiveUsers']);

// Bootstrap route - get all frequently accessed data in one request
Route::get('/bootstrap', [BootstrapController::class, 'index']);

// Public site settings route (for favicon, SEO, etc.)
Route::get('/site-settings', [SiteSettingsController::class, 'index']);
Route::get('/site-settings/timestamp', [SiteSettingsController::class, 'timestamp']);
Route::get('/site-settings/{key}', [SiteSettingsController::class, 'show']);

// Public shortcuts route (for chat expansion)
Route::get('/shortcuts/active', [ShortcutsController::class, 'getActive']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::post('/profile/premium-entry-background', [ProfileController::class, 'uploadPremiumEntryBackground']);
    Route::delete('/profile/premium-entry-background', [ProfileController::class, 'deletePremiumEntryBackground']);
    
    // Room routes
    Route::get('/chat/general', [RoomController::class, 'getGeneralRoom']);
    Route::get('/chat/{id}', [RoomController::class, 'show']);
    Route::post('/chat', [RoomController::class, 'store']);
    Route::put('/chat/{id}', [RoomController::class, 'update']);
    Route::delete('/chat/{id}', [RoomController::class, 'destroy']);
    Route::post('/chat/{id}/image', [RoomController::class, 'uploadImage']);
    
    // Room user management routes
    Route::post('/chat/{roomId}/users', [RoomController::class, 'addUser']);
    Route::delete('/chat/{roomId}/users/{userId}', [RoomController::class, 'removeUser']);
    Route::post('/chat/{roomId}/users/{userId}/mute', [RoomController::class, 'muteUser']);
    
    // Message routes
    Route::get('/chat/{roomId}/messages', [MessageController::class, 'index']);
    Route::post('/chat/{roomId}/messages', [MessageController::class, 'store']);
    
    // Private message routes
    Route::prefix('private-messages')->group(function () {
        Route::get('/', [PrivateMessageController::class, 'index']); // Get conversations list
        Route::get('/unread-count', [PrivateMessageController::class, 'unreadCount']); // Get unread count
        Route::get('/{userId}', [PrivateMessageController::class, 'show']); // Get messages with a user
        Route::post('/{userId}', [PrivateMessageController::class, 'store']); // Send message to a user
        Route::post('/{userId}/read', [PrivateMessageController::class, 'markAsRead']); // Mark messages as read
    });
    
    // Wall posts routes
    Route::get('/chat/{roomId}/wall-posts', [WallPostController::class, 'index']);
    Route::post('/chat/{roomId}/wall-posts', [WallPostController::class, 'store']);
    Route::delete('/chat/{roomId}/wall-posts/{id}', [WallPostController::class, 'destroy']);
    Route::post('/chat/{roomId}/wall-posts/{id}/like', [WallPostController::class, 'toggleLike']);
    Route::get('/chat/{roomId}/wall-posts/{id}/comments', [WallPostController::class, 'getComments']);
    Route::post('/chat/{roomId}/wall-posts/{id}/comments', [WallPostController::class, 'storeComment']);
    Route::delete('/chat/{roomId}/wall-posts/{postId}/comments/{commentId}', [WallPostController::class, 'deleteComment']);
    Route::get('/chat/{roomId}/wall-creator', [WallPostController::class, 'getWallCreator']);
    
    // Stories routes
    Route::get('/stories', [StoryController::class, 'index']);
    Route::get('/stories/user/{userId}', [StoryController::class, 'show']);
    Route::post('/stories', [StoryController::class, 'store']);
    Route::post('/stories/{storyId}/view', [StoryController::class, 'markAsViewed']);
    Route::delete('/stories/{storyId}', [StoryController::class, 'destroy']);
    Route::get('/stories/{storyId}/views', [StoryController::class, 'views']);
    
    // YouTube search route
    Route::get('/youtube/search', [YouTubeController::class, 'search']);
    
    // Role Group routes
    Route::apiResource('role-groups', RoleGroupController::class);
    Route::post('/role-groups/{id}/users', [RoleGroupController::class, 'assignUsers']);
    Route::delete('/role-groups/{id}/users', [RoleGroupController::class, 'removeUsers']);
    
    // User management routes (order matters - specific routes before parameterized ones)
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/premium-entry', [UserController::class, 'getPremiumEntryUsers']);
    Route::post('/users/{id}/premium-entry-background', [UserController::class, 'uploadPremiumEntryBackground']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    
    // Symbols management routes
    Route::apiResource('symbols', SymbolController::class);
    
    // Site settings routes (protected - for updates/management)
    Route::put('/site-settings/{key}', [SiteSettingsController::class, 'update']);
    Route::post('/site-settings/{key}/image', [SiteSettingsController::class, 'uploadImage']);
    Route::delete('/site-settings/{key}/image', [SiteSettingsController::class, 'deleteImage']);
    
    // Scheduled messages routes
    Route::apiResource('scheduled-messages', ScheduledMessageController::class);
    
    // Shortcuts management routes
    Route::apiResource('shortcuts', ShortcutsController::class);
    
    // Membership designs routes
    Route::get('/membership-designs', [MembershipDesignController::class, 'index']);
    Route::get('/membership-designs/{membershipDesign}', [MembershipDesignController::class, 'show']);
    
    // User design selection routes
    Route::put('/profile/designs', [ProfileController::class, 'updateDesigns']);
    
    // Admin routes for managing designs (permission check in controller)
    Route::post('/membership-designs', [MembershipDesignController::class, 'store']);
    Route::put('/membership-designs/{membershipDesign}', [MembershipDesignController::class, 'update']);
    Route::delete('/membership-designs/{membershipDesign}', [MembershipDesignController::class, 'destroy']);
    
    // Login logs routes
    Route::get('/login-logs', [LoginLogController::class, 'index']);
    
    // Ban management routes
    Route::prefix('bans')->group(function () {
        // Banned browsers
        Route::get('/browsers', [BanController::class, 'getBannedBrowsers']);
        Route::post('/browsers', [BanController::class, 'banBrowser']);
        Route::delete('/browsers/{id}', [BanController::class, 'unbanBrowser']);
        
        // Banned operating systems
        Route::get('/operating-systems', [BanController::class, 'getBannedOperatingSystems']);
        Route::post('/operating-systems', [BanController::class, 'banOperatingSystem']);
        Route::delete('/operating-systems/{id}', [BanController::class, 'unbanOperatingSystem']);
        
        // Banned users
        Route::get('/users', [BanController::class, 'getBannedUsers']);
        Route::post('/users', [BanController::class, 'banUser']);
        Route::post('/users/rate-limit', [BanController::class, 'banUserRateLimit']);
        Route::delete('/users/{id}', [BanController::class, 'unbanUser']);
        
        // Check ban status
        Route::get('/check', [BanController::class, 'checkBan']);
    });
    
    // Reports routes
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']); // Admin: get all reports
        Route::get('/my-reports', [ReportController::class, 'myReports']); // User: get their reports
        Route::post('/', [ReportController::class, 'store']); // User: create report
        Route::get('/{id}', [ReportController::class, 'show']); // Get specific report
        Route::put('/{id}', [ReportController::class, 'update']); // Admin: update report status
        Route::delete('/{id}', [ReportController::class, 'destroy']); // Admin: delete report
    });
    
    // Subscriptions routes
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']); // Get all subscriptions
        Route::put('/{id}', [SubscriptionController::class, 'update']); // Update user subscription
    });
    
    // Filtered words routes
    Route::apiResource('filtered-words', FilteredWordController::class);
    
    // Filtered word violations routes
    Route::prefix('filtered-word-violations')->group(function () {
        Route::get('/', [FilteredWordViolationController::class, 'index']);
        Route::get('/statistics', [FilteredWordViolationController::class, 'statistics']);
        Route::get('/{id}', [FilteredWordViolationController::class, 'show']);
        Route::put('/{id}', [FilteredWordViolationController::class, 'update']);
    });
    
    // User warnings routes
    Route::prefix('warnings')->group(function () {
        Route::get('/my-warnings', [UserWarningController::class, 'myWarnings']); // User's own warnings
        Route::put('/{id}/read', [UserWarningController::class, 'markAsRead']); // Mark as read
        Route::get('/user/{userId}', [UserWarningController::class, 'getUserWarnings']); // Admin: get user's warnings
        Route::post('/', [UserWarningController::class, 'store']); // Admin: create manual warning
        Route::delete('/{id}', [UserWarningController::class, 'destroy']); // Admin: delete warning
    });
    
    // User status routes (optimized for high-frequency updates)
    Route::prefix('user-status')->group(function () {
        Route::match(['PUT', 'POST'], '/', [UserStatusController::class, 'update']); // Update current user's status (supports sendBeacon)
        Route::post('/multiple', [UserStatusController::class, 'getMultiple']); // Get multiple user statuses
    });
});

// Broadcasting auth route (must be outside auth middleware group for Echo)
Route::post('/broadcasting/auth', function (Request $request) {
    // Check if user is authenticated
    if (!$request->user()) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    
    // Use Broadcast::auth() which handles channel authorization
    return Broadcast::auth($request);
})->middleware('auth:sanctum');
