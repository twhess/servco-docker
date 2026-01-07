<?php

namespace App\Services\Google;

use Google\Client as Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Gmail as Google_Service_Gmail;
use Google\Service\Sheets as Google_Service_Sheets;
use Illuminate\Support\Facades\Log;

/**
 * Google API Client service for authentication and client management.
 *
 * Handles service account authentication and provides configured
 * Google_Client instances for Drive, Sheets, and Gmail services.
 */
class GoogleClientService
{
    protected ?Google_Client $client = null;
    protected ?Google_Client $impersonatedClient = null;

    /**
     * Get a configured Google API client (no impersonation).
     *
     * Uses service account authentication via JSON key file.
     * Only has access to files explicitly shared with the service account.
     */
    public function getClient(): Google_Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = new Google_Client();
        $this->client->setApplicationName(config('app.name', 'ServcoApp'));

        // Service account authentication
        $keyFilePath = config('services.google.key_file_path');

        if ($keyFilePath && file_exists($keyFilePath)) {
            $this->client->setAuthConfig($keyFilePath);
        } elseif ($keyFilePath) {
            Log::warning('GoogleClientService: Key file not found', [
                'path' => $keyFilePath,
            ]);
        }

        // Set required scopes
        $this->client->setScopes([
            Google_Service_Drive::DRIVE,
            Google_Service_Sheets::SPREADSHEETS,
        ]);

        return $this->client;
    }

    /**
     * Get a configured Google API client with user impersonation.
     *
     * Uses domain-wide delegation to impersonate a user and access
     * their Drive/Gmail/etc. Requires domain-wide delegation setup
     * in Google Workspace Admin.
     */
    public function getImpersonatedClient(?string $impersonateEmail = null): Google_Client
    {
        $email = $impersonateEmail ?? $this->getGmailSharedMailbox();

        // Create a new client with impersonation
        $client = new Google_Client();
        $client->setApplicationName(config('app.name', 'ServcoApp'));

        $keyFilePath = config('services.google.key_file_path');

        if ($keyFilePath && file_exists($keyFilePath)) {
            $client->setAuthConfig($keyFilePath);
        }

        // Set scopes for both Gmail and Drive
        $client->setScopes([
            Google_Service_Gmail::GMAIL_READONLY,
            Google_Service_Gmail::GMAIL_MODIFY,
            Google_Service_Drive::DRIVE,
        ]);

        // Set subject for impersonation (required for domain-wide delegation)
        if ($email) {
            $client->setSubject($email);
        }

        return $client;
    }

    /**
     * Get a configured Google API client for Gmail with subject impersonation.
     *
     * @deprecated Use getImpersonatedClient() instead
     */
    public function getGmailClient(?string $impersonateEmail = null): Google_Client
    {
        return $this->getImpersonatedClient($impersonateEmail);
    }

    /**
     * Get a Google Drive service instance (no impersonation).
     * Only has access to files explicitly shared with the service account.
     */
    public function getDriveService(): Google_Service_Drive
    {
        return new Google_Service_Drive($this->getClient());
    }

    /**
     * Get a Google Drive service instance with user impersonation.
     * Has access to all files the impersonated user can access.
     */
    public function getImpersonatedDriveService(?string $impersonateEmail = null): Google_Service_Drive
    {
        return new Google_Service_Drive($this->getImpersonatedClient($impersonateEmail));
    }

    /**
     * Get a Google Sheets service instance.
     */
    public function getSheetsService(): Google_Service_Sheets
    {
        return new Google_Service_Sheets($this->getClient());
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        $keyFilePath = config('services.google.key_file_path');

        return !empty($keyFilePath) && file_exists($keyFilePath);
    }

    /**
     * Get the root folder ID for Drive operations.
     */
    public function getRootFolderId(): ?string
    {
        return config('services.google.drive.root_folder_id');
    }

    /**
     * Get the default spreadsheet ID for Sheets operations.
     */
    public function getDefaultSpreadsheetId(): ?string
    {
        return config('services.google.sheets.default_spreadsheet_id');
    }

    /**
     * Get a Gmail service instance.
     */
    public function getGmailService(?string $impersonateEmail = null): Google_Service_Gmail
    {
        return new Google_Service_Gmail($this->getGmailClient($impersonateEmail));
    }

    /**
     * Get the Gmail shared mailbox email address.
     */
    public function getGmailSharedMailbox(): ?string
    {
        return config('services.google.gmail.shared_mailbox');
    }

    /**
     * Get the Gmail attachments folder ID for Drive storage.
     */
    public function getGmailAttachmentsFolderId(): ?string
    {
        return config('services.google.gmail.attachments_folder_id');
    }

    /**
     * Check if Gmail is properly configured.
     */
    public function isGmailConfigured(): bool
    {
        $keyFilePath = config('services.google.key_file_path');
        $sharedMailbox = config('services.google.gmail.shared_mailbox');

        return !empty($keyFilePath)
            && file_exists($keyFilePath)
            && !empty($sharedMailbox);
    }
}
