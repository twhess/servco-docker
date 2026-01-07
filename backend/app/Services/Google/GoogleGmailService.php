<?php

namespace App\Services\Google;

use Google\Service\Gmail as Google_Service_Gmail;
use Google\Service\Gmail\ModifyMessageRequest;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

/**
 * Google Gmail service for email operations.
 *
 * Provides methods for reading emails, downloading attachments, and
 * managing Gmail messages using a service account with domain-wide delegation.
 *
 * Usage:
 *   $gmail = app(GoogleGmailService::class);
 *   $response = $gmail->listMessages(['maxResults' => 20]);
 */
class GoogleGmailService
{
    protected ?Google_Service_Gmail $service = null;
    protected ?Google_Service_Drive $driveService = null;
    protected ?string $sharedMailbox;

    public function __construct(
        protected GoogleClientService $clientService
    ) {
        $this->sharedMailbox = $clientService->getGmailSharedMailbox();

        if ($this->isConfigured()) {
            $this->service = $clientService->getGmailService();
            // Use impersonated Drive service to access user's Drive
            $this->driveService = $clientService->getImpersonatedDriveService();
        }
    }

    /**
     * List emails from inbox with optional filters.
     */
    public function listMessages(array $options = []): GoogleResponse
    {
        if (!$this->isConfigured()) {
            return GoogleResponse::error('Gmail not configured');
        }

        try {
            $params = [
                'maxResults' => $options['maxResults'] ?? 20,
                'labelIds' => $options['labelIds'] ?? ['INBOX'],
            ];

            if (!empty($options['q'])) {
                $params['q'] = $options['q'];
            }

            if (!empty($options['pageToken'])) {
                $params['pageToken'] = $options['pageToken'];
            }

            $response = $this->service->users_messages->listUsersMessages('me', $params);

            $messages = [];
            foreach ($response->getMessages() ?? [] as $message) {
                $messages[] = [
                    'id' => $message->getId(),
                    'thread_id' => $message->getThreadId(),
                ];
            }

            return GoogleResponse::success([
                'messages' => $messages,
                'next_page_token' => $response->getNextPageToken(),
                'result_size_estimate' => $response->getResultSizeEstimate(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: List messages failed', [
                'error' => $e->getMessage(),
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Get a single email by ID with full details.
     */
    public function getMessage(string $messageId): GoogleResponse
    {
        if (!$this->isConfigured()) {
            return GoogleResponse::error('Gmail not configured');
        }

        try {
            $message = $this->service->users_messages->get('me', $messageId, [
                'format' => 'full',
            ]);

            $headers = $this->parseHeaders($message->getPayload()->getHeaders());
            $attachments = [];
            $this->extractAttachments($message->getPayload(), $attachments);
            $body = $this->extractBody($message->getPayload());

            return GoogleResponse::success([
                'id' => $message->getId(),
                'thread_id' => $message->getThreadId(),
                'label_ids' => $message->getLabelIds(),
                'snippet' => $message->getSnippet(),
                'internal_date' => $message->getInternalDate(),
                'headers' => $headers,
                'subject' => $headers['Subject'] ?? '',
                'from' => $headers['From'] ?? '',
                'to' => $headers['To'] ?? '',
                'date' => $headers['Date'] ?? '',
                'body' => $body,
                'attachments' => $attachments,
                'has_attachments' => !empty($attachments),
            ], ['raw' => $message->toSimpleObject()]);
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: Get message failed', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Search emails with Gmail query syntax.
     */
    public function searchMessages(string $query, int $maxResults = 50): GoogleResponse
    {
        return $this->listMessages([
            'q' => $query,
            'maxResults' => $maxResults,
            'labelIds' => [], // Don't filter by label when searching
        ]);
    }

    /**
     * Get emails that have attachments.
     */
    public function getMessagesWithAttachments(int $maxResults = 50, ?string $pageToken = null): GoogleResponse
    {
        $options = [
            'q' => 'has:attachment',
            'maxResults' => $maxResults,
            'labelIds' => ['INBOX'],
        ];

        if ($pageToken) {
            $options['pageToken'] = $pageToken;
        }

        $listResponse = $this->listMessages($options);

        if ($listResponse->isError()) {
            return $listResponse;
        }

        // Fetch full details for each message
        $messagesWithDetails = [];
        foreach ($listResponse->getData()['messages'] ?? [] as $msg) {
            $detail = $this->getMessage($msg['id']);
            if ($detail->isSuccess()) {
                $messagesWithDetails[] = $detail->getData();
            }
        }

        return GoogleResponse::success([
            'messages' => $messagesWithDetails,
            'next_page_token' => $listResponse->getData()['next_page_token'] ?? null,
            'result_size_estimate' => $listResponse->getData()['result_size_estimate'] ?? 0,
        ]);
    }

    /**
     * Get attachment content by message ID and attachment ID.
     */
    public function getAttachment(string $messageId, string $attachmentId): GoogleResponse
    {
        if (!$this->isConfigured()) {
            return GoogleResponse::error('Gmail not configured');
        }

        try {
            $attachment = $this->service->users_messages_attachments->get(
                'me',
                $messageId,
                $attachmentId
            );

            // Decode base64url encoded data
            $data = $this->base64UrlDecode($attachment->getData());

            return GoogleResponse::success([
                'data' => $data,
                'size' => $attachment->getSize(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: Get attachment failed', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'attachment_id' => $attachmentId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Download attachment and save to Google Drive.
     * Uses impersonated Drive service to access user's Drive.
     */
    public function saveAttachmentToDrive(
        string $messageId,
        string $attachmentId,
        string $filename,
        ?string $folderId = null
    ): GoogleResponse {
        if (!$this->driveService) {
            return GoogleResponse::error('Drive service not configured');
        }

        // Get attachment content
        $attachmentResponse = $this->getAttachment($messageId, $attachmentId);

        if ($attachmentResponse->isError()) {
            return $attachmentResponse;
        }

        $content = $attachmentResponse->getData()['data'];
        $targetFolder = $folderId ?? $this->clientService->getGmailAttachmentsFolderId();
        $mimeType = $this->getMimeTypeFromFilename($filename);

        try {
            $fileMetadata = new DriveFile([
                'name' => $filename,
                'parents' => [$targetFolder ?? 'root'],
            ]);

            $file = $this->driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, name, webViewLink, webContentLink, mimeType, size',
                'supportsAllDrives' => true,  // Required for Shared Drives
            ]);

            Log::info('GoogleGmailService: Attachment saved to Drive', [
                'file_id' => $file->getId(),
                'filename' => $filename,
            ]);

            return GoogleResponse::file(
                fileId: $file->getId(),
                webViewLink: $file->getWebViewLink(),
                downloadLink: $file->getWebContentLink(),
                raw: ['file' => $file->toSimpleObject()]
            );
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: Save to Drive failed', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Download all attachments from a message to Drive.
     */
    public function saveAllAttachmentsToDrive(string $messageId, ?string $folderId = null): GoogleResponse
    {
        // Get message details to find attachments
        $messageResponse = $this->getMessage($messageId);

        if ($messageResponse->isError()) {
            return $messageResponse;
        }

        $attachments = $messageResponse->getData()['attachments'] ?? [];

        if (empty($attachments)) {
            return GoogleResponse::success([
                'message' => 'No attachments found',
                'downloaded' => [],
            ]);
        }

        $downloaded = [];
        $errors = [];

        foreach ($attachments as $attachment) {
            $result = $this->saveAttachmentToDrive(
                $messageId,
                $attachment['id'],
                $attachment['filename'],
                $folderId
            );

            if ($result->isSuccess()) {
                $downloaded[] = [
                    'filename' => $attachment['filename'],
                    'file_id' => $result->getFileId(),
                    'web_view_link' => $result->webViewLink,
                    'download_link' => $result->downloadLink,
                ];
            } else {
                $errors[] = [
                    'filename' => $attachment['filename'],
                    'error' => $result->getError(),
                ];
            }
        }

        return GoogleResponse::success([
            'downloaded' => $downloaded,
            'errors' => $errors,
            'total' => count($attachments),
            'success_count' => count($downloaded),
            'error_count' => count($errors),
        ]);
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(string $messageId): GoogleResponse
    {
        if (!$this->isConfigured()) {
            return GoogleResponse::error('Gmail not configured');
        }

        try {
            $modifyRequest = new ModifyMessageRequest();
            $modifyRequest->setRemoveLabelIds(['UNREAD']);

            $this->service->users_messages->modify('me', $messageId, $modifyRequest);

            return GoogleResponse::success(['marked_read' => true]);
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: Mark as read failed', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Add label to message.
     */
    public function addLabel(string $messageId, string $labelId): GoogleResponse
    {
        if (!$this->isConfigured()) {
            return GoogleResponse::error('Gmail not configured');
        }

        try {
            $modifyRequest = new ModifyMessageRequest();
            $modifyRequest->setAddLabelIds([$labelId]);

            $this->service->users_messages->modify('me', $messageId, $modifyRequest);

            return GoogleResponse::success(['label_added' => $labelId]);
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: Add label failed', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Get list of labels.
     */
    public function getLabels(): GoogleResponse
    {
        if (!$this->isConfigured()) {
            return GoogleResponse::error('Gmail not configured');
        }

        try {
            $response = $this->service->users_labels->listUsersLabels('me');

            $labels = [];
            foreach ($response->getLabels() ?? [] as $label) {
                $labels[] = [
                    'id' => $label->getId(),
                    'name' => $label->getName(),
                    'type' => $label->getType(),
                ];
            }

            return GoogleResponse::success(['labels' => $labels]);
        } catch (\Exception $e) {
            Log::error('GoogleGmailService: Get labels failed', [
                'error' => $e->getMessage(),
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Check if service is configured.
     */
    public function isConfigured(): bool
    {
        return $this->clientService->isGmailConfigured();
    }

    /**
     * Parse email headers into associative array.
     */
    protected function parseHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            $result[$header->getName()] = $header->getValue();
        }

        return $result;
    }

    /**
     * Extract attachments from message payload recursively.
     */
    protected function extractAttachments($payload, array &$attachments): void
    {
        // Handle parts recursively (for multipart messages)
        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                $this->extractAttachments($part, $attachments);
            }
        }

        // Check if this part is an attachment
        $body = $payload->getBody();
        if ($body && $body->getAttachmentId()) {
            $attachments[] = [
                'id' => $body->getAttachmentId(),
                'filename' => $payload->getFilename() ?: 'unnamed',
                'mime_type' => $payload->getMimeType(),
                'size' => $body->getSize(),
            ];
        }
    }

    /**
     * Extract email body (text and HTML).
     */
    protected function extractBody($payload): array
    {
        $body = ['text' => '', 'html' => ''];

        $this->extractBodyRecursive($payload, $body);

        return $body;
    }

    /**
     * Recursively extract body content.
     */
    protected function extractBodyRecursive($payload, array &$body): void
    {
        $mimeType = $payload->getMimeType();
        $data = $payload->getBody()->getData();

        if ($data) {
            $decodedData = $this->base64UrlDecode($data);

            if ($mimeType === 'text/plain' && empty($body['text'])) {
                $body['text'] = $decodedData;
            } elseif ($mimeType === 'text/html' && empty($body['html'])) {
                $body['html'] = $decodedData;
            }
        }

        // Process parts recursively
        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                $this->extractBodyRecursive($part, $body);
            }
        }
    }

    /**
     * Decode base64url encoded string.
     */
    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get MIME type from filename extension.
     */
    protected function getMimeTypeFromFilename(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
