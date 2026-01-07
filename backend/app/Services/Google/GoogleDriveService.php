<?php

namespace App\Services\Google;

use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Log;

/**
 * Google Drive service for file operations.
 *
 * Provides methods for uploading, downloading, listing, and managing
 * files in Google Drive using a service account.
 *
 * Usage:
 *   $drive = app(GoogleDriveService::class);
 *   $response = $drive->uploadFile('/path/to/file.pdf', 'documents/file.pdf');
 */
class GoogleDriveService
{
    protected Google_Service_Drive $service;
    protected ?string $rootFolderId;

    public function __construct(protected GoogleClientService $clientService)
    {
        $this->service = $clientService->getDriveService();
        $this->rootFolderId = $clientService->getRootFolderId();
    }

    /**
     * Upload a file from local path to Google Drive.
     */
    public function uploadFile(string $localPath, string $remoteName, ?string $folderId = null, ?string $mimeType = null): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        if (!file_exists($localPath)) {
            return GoogleResponse::error('Local file not found: ' . $localPath);
        }

        try {
            $fileMetadata = new DriveFile([
                'name' => $remoteName,
                'parents' => [$folderId ?? $this->rootFolderId ?? 'root'],
            ]);

            $content = file_get_contents($localPath);
            $mimeType = $mimeType ?? mime_content_type($localPath) ?? 'application/octet-stream';

            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, name, webViewLink, webContentLink, mimeType, size',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            Log::info('GoogleDriveService: File uploaded', [
                'file_id' => $file->getId(),
                'name' => $file->getName(),
            ]);

            return GoogleResponse::file(
                fileId: $file->getId(),
                webViewLink: $file->getWebViewLink(),
                downloadLink: $file->getWebContentLink(),
                raw: ['file' => $file->toSimpleObject()]
            );
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Upload failed', [
                'error' => $e->getMessage(),
                'local_path' => $localPath,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Upload content directly (without local file).
     */
    public function uploadContent(string $content, string $fileName, ?string $folderId = null, ?string $mimeType = null): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId ?? $this->rootFolderId ?? 'root'],
            ]);

            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType ?? 'application/octet-stream',
                'uploadType' => 'multipart',
                'fields' => 'id, name, webViewLink, webContentLink, mimeType, size',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            return GoogleResponse::file(
                fileId: $file->getId(),
                webViewLink: $file->getWebViewLink(),
                downloadLink: $file->getWebContentLink(),
                raw: ['file' => $file->toSimpleObject()]
            );
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Content upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $fileName,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Download a file's content by ID.
     */
    public function downloadFile(string $fileId): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $response = $this->service->files->get($fileId, [
                'alt' => 'media',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            $content = $response->getBody()->getContents();

            return GoogleResponse::success([
                'content' => $content,
                'file_id' => $fileId,
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Download failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Get file metadata.
     */
    public function getFileInfo(string $fileId): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, mimeType, size, webViewLink, webContentLink, createdTime, modifiedTime, parents',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            return GoogleResponse::success([
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'web_view_link' => $file->getWebViewLink(),
                'web_content_link' => $file->getWebContentLink(),
                'created_time' => $file->getCreatedTime(),
                'modified_time' => $file->getModifiedTime(),
                'parents' => $file->getParents(),
            ], ['file' => $file->toSimpleObject()]);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Get file info failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Delete a file by ID.
     */
    public function deleteFile(string $fileId): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $this->service->files->delete($fileId, [
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            Log::info('GoogleDriveService: File deleted', [
                'file_id' => $fileId,
            ]);

            return GoogleResponse::success(['deleted' => true, 'file_id' => $fileId]);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Delete failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * List files in a folder.
     */
    public function listFiles(?string $folderId = null, ?string $query = null, int $pageSize = 100): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $params = [
                'pageSize' => $pageSize,
                'fields' => 'files(id, name, mimeType, size, webViewLink, webContentLink, createdTime, modifiedTime)',
                'supportsAllDrives' => true,  // Support Shared Drives
                'includeItemsFromAllDrives' => true,  // Include Shared Drive items
            ];

            // Build query
            $queryParts = [];

            $targetFolder = $folderId ?? $this->rootFolderId;
            if ($targetFolder) {
                $queryParts[] = "'{$targetFolder}' in parents";
            }

            if ($query) {
                $queryParts[] = $query;
            }

            $queryParts[] = "trashed = false";

            if (!empty($queryParts)) {
                $params['q'] = implode(' and ', $queryParts);
            }

            $result = $this->service->files->listFiles($params);

            $files = [];
            foreach ($result->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'web_view_link' => $file->getWebViewLink(),
                    'web_content_link' => $file->getWebContentLink(),
                    'created_time' => $file->getCreatedTime(),
                    'modified_time' => $file->getModifiedTime(),
                ];
            }

            return GoogleResponse::success($files);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: List files failed', [
                'error' => $e->getMessage(),
                'folder_id' => $folderId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Create a folder in Drive.
     */
    public function createFolder(string $name, ?string $parentId = null): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $fileMetadata = new DriveFile([
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId ?? $this->rootFolderId ?? 'root'],
            ]);

            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id, name, webViewLink',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            Log::info('GoogleDriveService: Folder created', [
                'folder_id' => $folder->getId(),
                'name' => $folder->getName(),
            ]);

            return GoogleResponse::file(
                fileId: $folder->getId(),
                webViewLink: $folder->getWebViewLink(),
                raw: ['folder' => $folder->toSimpleObject()]
            );
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Create folder failed', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Share a file with a user.
     */
    public function shareFile(string $fileId, string $email, string $role = 'reader'): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            $permission = new Permission([
                'type' => 'user',
                'role' => $role, // reader, writer, commenter
                'emailAddress' => $email,
            ]);

            $result = $this->service->permissions->create($fileId, $permission, [
                'sendNotificationEmail' => false,
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            Log::info('GoogleDriveService: File shared', [
                'file_id' => $fileId,
                'email' => $email,
                'role' => $role,
            ]);

            return GoogleResponse::success([
                'permission_id' => $result->getId(),
                'file_id' => $fileId,
                'email' => $email,
                'role' => $role,
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Share failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'email' => $email,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Move a file to a different folder.
     */
    public function moveFile(string $fileId, string $newFolderId): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Drive not configured');
        }

        try {
            // Get current parents
            $file = $this->service->files->get($fileId, [
                'fields' => 'parents',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);
            $previousParents = implode(',', $file->getParents());

            // Move to new folder
            $file = $this->service->files->update($fileId, new DriveFile(), [
                'addParents' => $newFolderId,
                'removeParents' => $previousParents,
                'fields' => 'id, name, parents, webViewLink',
                'supportsAllDrives' => true,  // Support Shared Drives
            ]);

            return GoogleResponse::file(
                fileId: $file->getId(),
                webViewLink: $file->getWebViewLink(),
                raw: ['file' => $file->toSimpleObject()]
            );
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: Move failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Check if service is configured.
     */
    public function isConfigured(): bool
    {
        return $this->clientService->isConfigured();
    }
}
