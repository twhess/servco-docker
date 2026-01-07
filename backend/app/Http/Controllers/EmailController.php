<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use App\Models\EmailAttachment;
use App\Services\Google\GoogleGmailService;
use App\Services\Google\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailController extends Controller
{
    public function __construct(
        protected GoogleGmailService $gmailService,
        protected GoogleDriveService $driveService
    ) {}

    /**
     * List emails from Gmail (live query).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page_token' => 'nullable|string',
            'max_results' => 'nullable|integer|min:1|max:100',
            'has_attachments' => 'nullable|boolean',
            'search' => 'nullable|string|max:500',
        ]);

        // Build Gmail query
        $query = '';
        if ($request->boolean('has_attachments')) {
            $query = 'has:attachment';
        }
        if (!empty($validated['search'])) {
            $query .= ' ' . $validated['search'];
        }

        $options = [
            'maxResults' => $validated['max_results'] ?? 20,
            'pageToken' => $validated['page_token'] ?? null,
        ];
        if ($query) {
            $options['q'] = trim($query);
        }

        $response = $this->gmailService->listMessages($options);

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to fetch emails',
                'error' => $response->getError(),
            ], 500);
        }

        // Fetch full details for each message
        $messages = [];
        foreach ($response->getData()['messages'] ?? [] as $msg) {
            $detail = $this->gmailService->getMessage($msg['id']);
            if ($detail->isSuccess()) {
                $messages[] = $detail->getData();
            }
        }

        return response()->json([
            'data' => $messages,
            'next_page_token' => $response->getData()['next_page_token'] ?? null,
        ]);
    }

    /**
     * Get single email details from Gmail.
     */
    public function show(string $messageId)
    {
        $response = $this->gmailService->getMessage($messageId);

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to fetch email',
                'error' => $response->getError(),
            ], 404);
        }

        return response()->json([
            'data' => $response->getData(),
        ]);
    }

    /**
     * Search emails with Gmail query.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:500',
            'max_results' => 'nullable|integer|min:1|max:100',
        ]);

        $response = $this->gmailService->searchMessages(
            $validated['q'],
            $validated['max_results'] ?? 50
        );

        if ($response->isError()) {
            return response()->json([
                'message' => 'Search failed',
                'error' => $response->getError(),
            ], 500);
        }

        // Fetch full details for each message
        $messages = [];
        foreach ($response->getData()['messages'] ?? [] as $msg) {
            $detail = $this->gmailService->getMessage($msg['id']);
            if ($detail->isSuccess()) {
                $messages[] = $detail->getData();
            }
        }

        return response()->json([
            'data' => $messages,
        ]);
    }

    /**
     * Get emails that have attachments.
     */
    public function withAttachments(Request $request)
    {
        $validated = $request->validate([
            'max_results' => 'nullable|integer|min:1|max:100',
            'page_token' => 'nullable|string',
        ]);

        $response = $this->gmailService->getMessagesWithAttachments(
            $validated['max_results'] ?? 50,
            $validated['page_token'] ?? null
        );

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to fetch emails with attachments',
                'error' => $response->getError(),
            ], 500);
        }

        return response()->json([
            'data' => $response->getData()['messages'] ?? [],
            'next_page_token' => $response->getData()['next_page_token'] ?? null,
        ]);
    }

    /**
     * Download attachment and save to Google Drive.
     */
    public function downloadAttachmentToDrive(Request $request, string $messageId, string $attachmentId)
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'folder_id' => 'nullable|string',
        ]);

        $folderId = $validated['folder_id'] ?? config('services.google.gmail.attachments_folder_id');

        $response = $this->gmailService->saveAttachmentToDrive(
            $messageId,
            $attachmentId,
            $validated['filename'],
            $folderId
        );

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to download attachment',
                'error' => $response->getError(),
            ], 500);
        }

        // Track in database if message exists locally
        $this->trackAttachmentDownload($messageId, $attachmentId, $response, $request->user());

        return response()->json([
            'message' => 'Attachment saved to Google Drive',
            'data' => [
                'file_id' => $response->getFileId(),
                'web_view_link' => $response->webViewLink,
                'download_link' => $response->downloadLink,
            ],
        ]);
    }

    /**
     * Download all attachments from a message to Drive.
     */
    public function downloadAllAttachments(Request $request, string $messageId)
    {
        $validated = $request->validate([
            'folder_id' => 'nullable|string',
        ]);

        $folderId = $validated['folder_id'] ?? config('services.google.gmail.attachments_folder_id');

        $response = $this->gmailService->saveAllAttachmentsToDrive($messageId, $folderId);

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to download attachments',
                'error' => $response->getError(),
            ], 500);
        }

        return response()->json([
            'message' => 'All attachments saved to Google Drive',
            'data' => $response->getData(),
        ]);
    }

    /**
     * Mark email as read.
     */
    public function markAsRead(string $messageId)
    {
        $response = $this->gmailService->markAsRead($messageId);

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to mark as read',
                'error' => $response->getError(),
            ], 500);
        }

        return response()->json([
            'message' => 'Email marked as read',
        ]);
    }

    /**
     * Get Gmail labels.
     */
    public function labels()
    {
        $response = $this->gmailService->getLabels();

        if ($response->isError()) {
            return response()->json([
                'message' => 'Failed to fetch labels',
                'error' => $response->getError(),
            ], 500);
        }

        return response()->json([
            'data' => $response->getData()['labels'] ?? [],
        ]);
    }

    /**
     * Check Gmail sync status.
     */
    public function syncStatus()
    {
        return response()->json([
            'data' => [
                'configured' => $this->gmailService->isConfigured(),
                'shared_mailbox' => config('services.google.gmail.shared_mailbox'),
                'attachments_folder_configured' => !empty(config('services.google.gmail.attachments_folder_id')),
            ],
        ]);
    }

    // ========== LOCAL DATABASE OPERATIONS ==========

    /**
     * Get locally cached emails.
     */
    public function localIndex(Request $request)
    {
        $query = EmailMessage::with('attachments')
            ->orderBy('email_date', 'desc');

        if ($request->boolean('has_attachments')) {
            $query->withAttachments();
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        return response()->json(
            $query->paginate($request->get('per_page', 20))
        );
    }

    /**
     * Get single local email.
     */
    public function localShow($id)
    {
        $email = EmailMessage::with('attachments')->findOrFail($id);

        return response()->json([
            'data' => $email,
        ]);
    }

    /**
     * Sync emails from Gmail to local database.
     */
    public function sync(Request $request)
    {
        $validated = $request->validate([
            'max_results' => 'nullable|integer|min:1|max:100',
            'has_attachments_only' => 'nullable|boolean',
        ]);

        $maxResults = $validated['max_results'] ?? 50;
        $attachmentsOnly = $request->boolean('has_attachments_only', true);

        // Fetch from Gmail
        if ($attachmentsOnly) {
            $response = $this->gmailService->getMessagesWithAttachments($maxResults);
            $messages = $response->getData()['messages'] ?? [];
        } else {
            $listResponse = $this->gmailService->listMessages(['maxResults' => $maxResults]);
            if ($listResponse->isError()) {
                return response()->json([
                    'message' => 'Sync failed',
                    'error' => $listResponse->getError(),
                ], 500);
            }

            // Fetch full details
            $messages = [];
            foreach ($listResponse->getData()['messages'] ?? [] as $msg) {
                $detail = $this->gmailService->getMessage($msg['id']);
                if ($detail->isSuccess()) {
                    $messages[] = $detail->getData();
                }
            }
        }

        if (isset($response) && $response->isError()) {
            return response()->json([
                'message' => 'Sync failed',
                'error' => $response->getError(),
            ], 500);
        }

        $synced = 0;
        $skipped = 0;

        foreach ($messages as $data) {
            // Check if already exists
            if (EmailMessage::where('gmail_message_id', $data['id'])->exists()) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($data, $request, &$synced) {
                $emailMessage = EmailMessage::create([
                    'gmail_message_id' => $data['id'],
                    'gmail_thread_id' => $data['thread_id'],
                    'subject' => $data['subject'] ?? null,
                    'from_email' => $this->extractEmail($data['from'] ?? ''),
                    'from_name' => $this->extractName($data['from'] ?? ''),
                    'to_emails' => $this->parseToAddresses($data['to'] ?? ''),
                    'email_date' => $this->parseGmailDate($data['internal_date']),
                    'snippet' => $data['snippet'] ?? null,
                    'body_text' => $data['body']['text'] ?? null,
                    'body_html' => $data['body']['html'] ?? null,
                    'has_attachments' => $data['has_attachments'] ?? false,
                    'attachment_count' => count($data['attachments'] ?? []),
                    'status' => 'unprocessed',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                // Create attachment records
                foreach ($data['attachments'] ?? [] as $att) {
                    EmailAttachment::create([
                        'email_message_id' => $emailMessage->id,
                        'gmail_attachment_id' => $att['id'],
                        'filename' => $att['filename'],
                        'mime_type' => $att['mime_type'],
                        'file_size' => $att['size'],
                        'status' => 'pending',
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]);
                }

                $synced++;
            });
        }

        return response()->json([
            'message' => "Sync completed: {$synced} new, {$skipped} skipped",
            'data' => [
                'synced' => $synced,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * Update local email status.
     */
    public function updateStatus(Request $request, $id)
    {
        $email = EmailMessage::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:unprocessed,processing,processed,error',
            'processing_notes' => 'nullable|string',
        ]);

        $email->update([
            'status' => $validated['status'],
            'processing_notes' => $validated['processing_notes'] ?? null,
            'processed_at' => $validated['status'] === 'processed' ? now() : null,
            'processed_by' => $validated['status'] === 'processed' ? $request->user()->id : null,
        ]);

        return response()->json([
            'message' => 'Status updated',
            'data' => $email->fresh('attachments'),
        ]);
    }

    // ========== HELPER METHODS ==========

    /**
     * Extract email address from "Name <email>" format.
     */
    protected function extractEmail(string $from): string
    {
        if (preg_match('/<(.+)>/', $from, $matches)) {
            return $matches[1];
        }

        return trim($from);
    }

    /**
     * Extract name from "Name <email>" format.
     */
    protected function extractName(string $from): ?string
    {
        if (preg_match('/^(.+?)\s*</', $from, $matches)) {
            return trim($matches[1], ' "\'');
        }

        return null;
    }

    /**
     * Parse comma-separated To addresses.
     */
    protected function parseToAddresses(string $to): array
    {
        return array_map('trim', explode(',', $to));
    }

    /**
     * Parse Gmail internal date (milliseconds timestamp).
     */
    protected function parseGmailDate(string $internalDate): \DateTime
    {
        return \DateTime::createFromFormat('U', substr($internalDate, 0, 10));
    }

    /**
     * Track attachment download in local database.
     */
    protected function trackAttachmentDownload($messageId, $attachmentId, $response, $user): void
    {
        $attachment = EmailAttachment::where('gmail_attachment_id', $attachmentId)
            ->whereHas('emailMessage', fn($q) => $q->where('gmail_message_id', $messageId))
            ->first();

        if ($attachment) {
            $attachment->update([
                'status' => 'downloaded',
                'drive_file_id' => $response->getFileId(),
                'drive_web_view_link' => $response->webViewLink,
                'drive_download_link' => $response->downloadLink,
                'downloaded_at' => now(),
                'downloaded_by' => $user->id,
            ]);
        }
    }
}
