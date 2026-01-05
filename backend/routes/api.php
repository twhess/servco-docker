<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ServiceLocationController;
use App\Http\Controllers\PartsRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RunInstanceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ClosedDateController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerImportController;
use App\Http\Controllers\CustomerMergeController;

Route::get('/health', function () {
    return response()->json(['ok' => true]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Image serving routes - outside auth middleware because <img> tags can't send auth headers
// These routes use opaque IDs (no direct file paths exposed) for basic security
Route::get('/parts-requests/{id}/images/{imageId}', [PartsRequestController::class, 'showImage']);
Route::get('/parts-requests/{id}/images/{imageId}/thumbnail', [PartsRequestController::class, 'showImageThumbnail']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [AuthController::class, 'users']);
    Route::put('/users/{id}', [AuthController::class, 'updateUser']);
    Route::post('/users/{id}/toggle-active', [AuthController::class, 'toggleUserActive']);

    // Profile management
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);

    // Service Locations
    Route::get('/locations', [ServiceLocationController::class, 'index']);
    Route::get('/locations/{id}', [ServiceLocationController::class, 'show']);
    Route::post('/locations', [ServiceLocationController::class, 'store']);
    Route::put('/locations/{id}', [ServiceLocationController::class, 'update']);
    Route::post('/locations/{id}/assign-user', [ServiceLocationController::class, 'assignUser']);
    Route::post('/locations/{id}/status', [ServiceLocationController::class, 'updateStatus']);
    Route::post('/locations/{id}/position', [ServiceLocationController::class, 'recordPosition']);
    Route::get('/locations/{id}/position-history', [ServiceLocationController::class, 'positionHistory']);
    Route::delete('/locations/{id}', [ServiceLocationController::class, 'destroy']);
    Route::post('/locations/{id}/restore', [ServiceLocationController::class, 'restore']);

    // Location Contacts
    Route::post('/locations/{id}/phones', [ServiceLocationController::class, 'addPhone']);
    Route::put('/locations/{id}/phones/{phoneId}', [ServiceLocationController::class, 'updatePhone']);
    Route::delete('/locations/{id}/phones/{phoneId}', [ServiceLocationController::class, 'deletePhone']);
    Route::post('/locations/{id}/emails', [ServiceLocationController::class, 'addEmail']);
    Route::put('/locations/{id}/emails/{emailId}', [ServiceLocationController::class, 'updateEmail']);
    Route::delete('/locations/{id}/emails/{emailId}', [ServiceLocationController::class, 'deleteEmail']);

    // Parts Requests
    Route::get('/parts-requests/lookups', [PartsRequestController::class, 'lookups']);
    Route::get('/parts-requests/my-jobs', [PartsRequestController::class, 'myJobs']);
    Route::get('/parts-requests', [PartsRequestController::class, 'index']);
    Route::get('/parts-requests/{id}', [PartsRequestController::class, 'show']);
    Route::post('/parts-requests', [PartsRequestController::class, 'store']);
    Route::put('/parts-requests/{id}', [PartsRequestController::class, 'update']);
    Route::delete('/parts-requests/{id}', [PartsRequestController::class, 'destroy']);

    // Parts Request Assignment
    Route::post('/parts-requests/{id}/assign', [PartsRequestController::class, 'assign']);
    Route::post('/parts-requests/{id}/unassign', [PartsRequestController::class, 'unassign']);

    // Parts Request Events & Timeline
    Route::post('/parts-requests/{id}/events', [PartsRequestController::class, 'addEvent']);
    Route::get('/parts-requests/{id}/timeline', [PartsRequestController::class, 'timeline']);

    // Parts Request Photos
    Route::post('/parts-requests/{id}/photos', [PartsRequestController::class, 'uploadPhoto']);
    Route::get('/parts-requests/{id}/photos', [PartsRequestController::class, 'photos']);

    // Parts Request GPS Tracking
    Route::post('/parts-requests/{id}/location', [PartsRequestController::class, 'postLocation']);
    Route::get('/parts-requests/{id}/tracking', [PartsRequestController::class, 'tracking']);

    // Parts Request Line Items
    Route::get('/parts-requests/{id}/items', [PartsRequestController::class, 'items']);
    Route::post('/parts-requests/{id}/items', [PartsRequestController::class, 'addItem']);
    Route::put('/parts-requests/{id}/items/{itemId}', [PartsRequestController::class, 'updateItem']);
    Route::delete('/parts-requests/{id}/items/{itemId}', [PartsRequestController::class, 'removeItem']);
    Route::post('/parts-requests/{id}/items/{itemId}/verify', [PartsRequestController::class, 'verifyItem']);
    Route::post('/parts-requests/{id}/items/{itemId}/unverify', [PartsRequestController::class, 'unverifyItem']);
    Route::put('/parts-requests/{id}/items/reorder', [PartsRequestController::class, 'reorderItems']);

    // Parts Request Documents
    Route::get('/parts-requests/{id}/documents', [PartsRequestController::class, 'documents']);
    Route::post('/parts-requests/{id}/documents', [PartsRequestController::class, 'uploadDocument']);
    Route::get('/parts-requests/{id}/documents/{documentId}', [PartsRequestController::class, 'showDocument']);
    Route::put('/parts-requests/{id}/documents/{documentId}', [PartsRequestController::class, 'updateDocument']);
    Route::delete('/parts-requests/{id}/documents/{documentId}', [PartsRequestController::class, 'deleteDocument']);
    Route::get('/parts-requests/{id}/documents/{documentId}/download', [PartsRequestController::class, 'downloadDocument']);

    // Parts Request Images (showImage and showImageThumbnail are public routes above)
    Route::get('/parts-requests/{id}/images', [PartsRequestController::class, 'images']);
    Route::post('/parts-requests/{id}/images', [PartsRequestController::class, 'uploadImage']);
    Route::put('/parts-requests/{id}/images/{imageId}', [PartsRequestController::class, 'updateImage']);
    Route::delete('/parts-requests/{id}/images/{imageId}', [PartsRequestController::class, 'deleteImage']);

    // Parts Request Notes
    Route::get('/parts-requests/{id}/notes', [PartsRequestController::class, 'notes']);
    Route::post('/parts-requests/{id}/notes', [PartsRequestController::class, 'storeNote']);
    Route::put('/parts-requests/{id}/notes/{noteId}', [PartsRequestController::class, 'updateNote']);
    Route::delete('/parts-requests/{id}/notes/{noteId}', [PartsRequestController::class, 'deleteNote']);

    // Roles & Permissions
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::post('/users/{userId}/roles', [RoleController::class, 'assignToUser']);

    // Audit Logs
    Route::get('/audit-logs/recent', [AuditLogController::class, 'recent']);
    Route::get('/audit-logs/statistics', [AuditLogController::class, 'statistics']);
    Route::get('/audit-logs/user/{userId}', [AuditLogController::class, 'userActivity']);
    Route::get('/audit-logs/{modelType}/{modelId}', [AuditLogController::class, 'show']);

    // ==========================================
    // PARTS RUNNER ROUTING ROUTES (NEW)
    // ==========================================

    // Routes Management
    Route::get('/routes', [RouteController::class, 'index']);
    Route::get('/routes/{id}', [RouteController::class, 'show']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);
    Route::post('/routes/{id}/activate', [RouteController::class, 'activate']);

    // Route Stops Management
    Route::post('/routes/{id}/stops', [RouteController::class, 'addStop']);
    Route::put('/routes/{id}/stops/{stopId}', [RouteController::class, 'updateStop']);
    Route::delete('/routes/{id}/stops/{stopId}', [RouteController::class, 'removeStop']);
    Route::post('/routes/{id}/stops/reorder', [RouteController::class, 'reorderStops']);

    // Route Schedules Management
    Route::post('/routes/{id}/schedules', [RouteController::class, 'addSchedule']);
    Route::put('/routes/{id}/schedules/{scheduleId}', [RouteController::class, 'updateSchedule']);
    Route::delete('/routes/{id}/schedules/{scheduleId}', [RouteController::class, 'removeSchedule']);

    // Route Graph & Pathfinding
    Route::post('/routes/rebuild-cache', [RouteController::class, 'rebuildCache']);
    Route::get('/routes/path', [RouteController::class, 'findPath']);

    // Run Instances
    Route::get('/runs', [RunInstanceController::class, 'index']);
    Route::get('/runs/my-runs', [RunInstanceController::class, 'myRuns']);
    Route::post('/runs/create-on-demand', [RunInstanceController::class, 'createOnDemand']);
    Route::get('/runs/{id}', [RunInstanceController::class, 'show']);
    Route::post('/runs/{id}/assign', [RunInstanceController::class, 'assign']);
    Route::post('/runs/{id}/start', [RunInstanceController::class, 'start']);
    Route::post('/runs/{id}/complete', [RunInstanceController::class, 'complete']);
    Route::post('/runs/{id}/merge/{sourceId}', [RunInstanceController::class, 'merge']);
    Route::put('/runs/{id}/current-stop', [RunInstanceController::class, 'updateCurrentStop']);

    // Run Stop Tracking
    Route::post('/runs/{id}/stops/{stopId}/arrive', [RunInstanceController::class, 'arriveAtStop']);
    Route::post('/runs/{id}/stops/{stopId}/depart', [RunInstanceController::class, 'departFromStop']);

    // Run Notes
    Route::post('/runs/{id}/notes', [RunInstanceController::class, 'addNote']);
    Route::get('/runs/{id}/notes', [RunInstanceController::class, 'getNotes']);

    // Parts Requests - Enhanced Routing Endpoints
    Route::post('/parts-requests/{id}/actions/{action}', [PartsRequestController::class, 'executeAction']);
    Route::get('/parts-requests/{id}/available-actions', [PartsRequestController::class, 'availableActions']);
    Route::post('/parts-requests/{id}/assign-to-run', [PartsRequestController::class, 'assignToRun']);
    Route::post('/parts-requests/{id}/not-ready', [PartsRequestController::class, 'markNotReady']);
    Route::get('/parts-requests/{id}/segments', [PartsRequestController::class, 'segments']);
    Route::get('/parts-requests/needs-staging', [PartsRequestController::class, 'needsStaging']);
    Route::get('/parts-requests/feed', [PartsRequestController::class, 'feed']);
    Route::post('/parts-requests/{id}/link-item', [PartsRequestController::class, 'linkItem']);
    Route::get('/parts-requests/scheduled', [PartsRequestController::class, 'scheduled']);
    Route::post('/parts-requests/bulk-schedule', [PartsRequestController::class, 'bulkSchedule']);

    // Inventory Items - QR Scanning & Movement Tracking
    Route::get('/items/scan/{qrCode}', [ItemController::class, 'scan']);
    Route::get('/items/{id}/movement-history', [ItemController::class, 'movementHistory']);
    Route::get('/items/{id}/current-request', [ItemController::class, 'currentRequest']);

    // Closed Dates (Business Calendar)
    Route::get('/closed-dates', [ClosedDateController::class, 'index']);
    Route::post('/closed-dates', [ClosedDateController::class, 'store']);
    Route::get('/closed-dates/check', [ClosedDateController::class, 'checkDate']);
    Route::get('/closed-dates/{id}', [ClosedDateController::class, 'show']);
    Route::put('/closed-dates/{id}', [ClosedDateController::class, 'update']);
    Route::delete('/closed-dates/{id}', [ClosedDateController::class, 'destroy']);

    // ==========================================
    // VENDORS & ADDRESSES
    // ==========================================

    // Vendors
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/search', [VendorController::class, 'search']);
    Route::post('/vendors/check-duplicate', [VendorController::class, 'checkDuplicate']);
    Route::post('/vendors', [VendorController::class, 'store']);
    Route::get('/vendors/{id}', [VendorController::class, 'show']);
    Route::put('/vendors/{id}', [VendorController::class, 'update']);
    Route::delete('/vendors/{id}', [VendorController::class, 'destroy']);

    // Vendor Addresses
    Route::post('/vendors/{id}/addresses', [VendorController::class, 'attachAddress']);
    Route::put('/vendors/{id}/addresses/{addressId}', [VendorController::class, 'updateAddressPivot']);
    Route::delete('/vendors/{id}/addresses/{addressId}', [VendorController::class, 'detachAddress']);

    // Addresses (standalone)
    Route::apiResource('addresses', AddressController::class);

    // ==========================================
    // CUSTOMERS
    // ==========================================

    // Customers
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/search', [CustomerController::class, 'search']);
    Route::post('/customers/check-duplicate', [CustomerController::class, 'checkDuplicate']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    // Customer Addresses
    Route::post('/customers/{id}/addresses', [CustomerController::class, 'attachAddress']);
    Route::put('/customers/{id}/addresses/{addressId}', [CustomerController::class, 'updateAddressPivot']);
    Route::delete('/customers/{id}/addresses/{addressId}', [CustomerController::class, 'detachAddress']);

    // Customer Imports
    Route::get('/customer-imports', [CustomerImportController::class, 'index']);
    Route::post('/customer-imports', [CustomerImportController::class, 'store']);
    Route::get('/customer-imports/{id}', [CustomerImportController::class, 'show']);
    Route::get('/customer-imports/{id}/rows', [CustomerImportController::class, 'rows']);
    Route::post('/customer-imports/{id}/process', [CustomerImportController::class, 'process']);
    Route::delete('/customer-imports/{id}', [CustomerImportController::class, 'destroy']);

    // Customer Merge Candidates
    Route::get('/customer-merges', [CustomerMergeController::class, 'index']);
    Route::get('/customer-merges/summary', [CustomerMergeController::class, 'summary']);
    Route::get('/customer-merges/{id}', [CustomerMergeController::class, 'show']);
    Route::post('/customer-merges/{id}/resolve', [CustomerMergeController::class, 'resolve']);
    Route::post('/customer-merges/batch-resolve', [CustomerMergeController::class, 'batchResolve']);
});
