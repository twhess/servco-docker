<?php

namespace App\Http\Controllers;

use App\Services\Gemini\GeminiService;
use App\Services\Google\GoogleClientService;
use App\Services\Python\PythonCsvService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GeminiController extends Controller
{
    public function __construct(
        protected GeminiService $gemini,
        protected GoogleClientService $googleClient,
        protected PythonCsvService $pythonCsv
    ) {}

    /**
     * Check service status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'data' => [
                'gemini_configured' => $this->gemini->isConfigured(),
                'gemini_model' => $this->gemini->getModel(),
                'drive_configured' => $this->googleClient->isConfigured(),
                'python_available' => $this->pythonCsv->isAvailable(),
                'python_version' => $this->pythonCsv->getVersion(),
            ],
        ]);
    }

    /**
     * List Drive folders.
     */
    public function listFolders(Request $request): JsonResponse
    {
        $parentId = $request->query('parent_id');

        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            $query = "mimeType='application/vnd.google-apps.folder' and trashed=false";
            if ($parentId) {
                $query .= " and '{$parentId}' in parents";
            }

            $result = $driveService->files->listFiles([
                'q' => $query,
                'pageSize' => 100,
                'fields' => 'files(id, name, parents)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'orderBy' => 'name',
            ]);

            $folders = [];
            foreach ($result->getFiles() as $file) {
                $folders[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'parents' => $file->getParents(),
                ];
            }

            return response()->json(['data' => $folders]);
        } catch (\Exception $e) {
            Log::error('GeminiController: List folders failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to list folders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search Drive folders by name.
     */
    public function searchFolders(Request $request): JsonResponse
    {
        $searchTerm = $request->query('q');

        if (!$searchTerm || strlen($searchTerm) < 2) {
            return response()->json(['message' => 'Search term must be at least 2 characters'], 400);
        }

        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Search for folders containing the search term (case-insensitive)
            $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and name contains '{$searchTerm}'";

            $result = $driveService->files->listFiles([
                'q' => $query,
                'pageSize' => 50,
                'fields' => 'files(id, name, parents)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'orderBy' => 'name',
            ]);

            $folders = [];
            foreach ($result->getFiles() as $file) {
                $folders[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'parents' => $file->getParents(),
                ];
            }

            return response()->json(['data' => $folders]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Search folders failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to search folders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List CSV files in a folder.
     */
    public function listCsvFiles(Request $request): JsonResponse
    {
        $folderId = $request->query('folder_id');

        if (!$folderId) {
            return response()->json(['message' => 'folder_id is required'], 400);
        }

        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Query for CSV files and Google Sheets
            $query = "'{$folderId}' in parents and trashed=false and (mimeType='text/csv' or mimeType='application/vnd.google-apps.spreadsheet' or name contains '.csv')";

            $result = $driveService->files->listFiles([
                'q' => $query,
                'pageSize' => 100,
                'fields' => 'files(id, name, mimeType, size, modifiedTime, webViewLink)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'orderBy' => 'name',
            ]);

            $files = [];
            foreach ($result->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'modified_time' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink(),
                ];
            }

            return response()->json(['data' => $files]);
        } catch (\Exception $e) {
            Log::error('GeminiController: List CSV files failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to list files',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get CSV file content and parse it.
     */
    public function getCsvContent(Request $request, string $fileId): JsonResponse
    {
        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Get file metadata first
            $fileMeta = $driveService->files->get($fileId, [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            $mimeType = $fileMeta->getMimeType();
            $content = '';

            // Handle Google Sheets - export as CSV
            if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                $response = $driveService->files->export($fileId, 'text/csv', [
                    'alt' => 'media',
                ]);
                $content = $response->getBody()->getContents();
            } else {
                // Regular file - download directly
                $response = $driveService->files->get($fileId, [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                $content = $response->getBody()->getContents();
            }

            // Parse CSV
            $lines = str_getcsv($content, "\n");
            $headers = [];
            $rows = [];

            foreach ($lines as $index => $line) {
                $row = str_getcsv($line);

                if ($index === 0) {
                    $headers = $row;
                } else {
                    // Create associative array with headers
                    if (count($row) === count($headers)) {
                        $rows[] = array_combine($headers, $row);
                    } else {
                        $rows[] = $row;
                    }
                }
            }

            return response()->json([
                'data' => [
                    'file_id' => $fileId,
                    'file_name' => $fileMeta->getName(),
                    'headers' => $headers,
                    'rows' => $rows,
                    'row_count' => count($rows),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Get CSV content failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get file content',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query CSV data using Gemini.
     */
    public function queryCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => 'required|string',
            'query' => 'required|string|max:2000',
            'include_data' => 'boolean',
        ]);

        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Get file metadata
            $fileMeta = $driveService->files->get($validated['file_id'], [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            $mimeType = $fileMeta->getMimeType();
            $content = '';

            // Handle Google Sheets - export as CSV
            if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                $response = $driveService->files->export($validated['file_id'], 'text/csv', [
                    'alt' => 'media',
                ]);
                $content = $response->getBody()->getContents();
            } else {
                $response = $driveService->files->get($validated['file_id'], [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                $content = $response->getBody()->getContents();
            }

            // Limit content size to avoid token limits (500KB for single file)
            // Gemini 2.0 Flash supports ~1M token context, so this is well within limits
            $maxChars = 500000;
            if (strlen($content) > $maxChars) {
                $content = substr($content, 0, $maxChars) . "\n... (truncated)";
            }

            $systemPrompt = <<<PROMPT
You are a data analyst assistant. You help users query and analyze CSV data.

CRITICAL INSTRUCTIONS:
- When asked to filter data (e.g., "get customers with X", "show records where Y"), you MUST ONLY return records that EXACTLY match the filter criteria
- Do NOT include records that don't match the filter - be strict about filtering
- If a user asks for records "with Dayton address", only include rows where an address field contains "Dayton"
- If no records match the filter, clearly state "No records found matching the criteria"

When analyzing data:
- Be precise with numbers and calculations
- If asked for specific records, show ONLY those records in a clear format (use markdown tables)
- If asked for summaries or aggregations, calculate them accurately
- Format monetary values with $ and commas
- Format dates in a readable way
- Use markdown tables for tabular output
PROMPT;

            $userPrompt = <<<PROMPT
Here is CSV data from file "{$fileMeta->getName()}":

{$content}

User question: {$validated['query']}

IMPORTANT: If this is a filtering request, return ONLY the records that match the filter criteria. Do not include non-matching records. Use a markdown table for the results.
PROMPT;

            $response = $this->gemini->generateWithSystem($systemPrompt, $userPrompt, [
                'temperature' => 0.3,
                'max_tokens' => 4096,
            ]);

            if ($response->isError()) {
                return response()->json([
                    'message' => 'Gemini query failed',
                    'error' => $response->getError(),
                ], 500);
            }

            return response()->json([
                'data' => [
                    'file_name' => $fileMeta->getName(),
                    'query' => $validated['query'],
                    'response' => $response->getText(),
                    'tokens_used' => $response->getTotalTokens(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Query CSV failed', [
                'file_id' => $validated['file_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to query CSV',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query multiple CSV files.
     */
    public function queryMultipleCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_ids' => 'required|array|min:1|max:10',
            'file_ids.*' => 'string',
            'query' => 'required|string|max:2000',
        ]);

        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            $allContent = [];

            foreach ($validated['file_ids'] as $fileId) {
                $fileMeta = $driveService->files->get($fileId, [
                    'fields' => 'id, name, mimeType',
                    'supportsAllDrives' => true,
                ]);

                $mimeType = $fileMeta->getMimeType();

                if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                    $response = $driveService->files->export($fileId, 'text/csv', [
                        'alt' => 'media',
                    ]);
                    $content = $response->getBody()->getContents();
                } else {
                    $response = $driveService->files->get($fileId, [
                        'alt' => 'media',
                        'supportsAllDrives' => true,
                    ]);
                    $content = $response->getBody()->getContents();
                }

                // Limit each file (100KB per file for multi-file queries)
                $maxChars = 100000;
                if (strlen($content) > $maxChars) {
                    $content = substr($content, 0, $maxChars) . "\n... (truncated)";
                }

                $allContent[] = "=== File: {$fileMeta->getName()} ===\n{$content}";
            }

            $combinedContent = implode("\n\n", $allContent);

            $systemPrompt = <<<PROMPT
You are a data analyst assistant. You help users query and analyze CSV data from multiple files.

CRITICAL INSTRUCTIONS:
- When asked to filter data (e.g., "get customers with X", "show records where Y"), you MUST ONLY return records that EXACTLY match the filter criteria
- Do NOT include records that don't match the filter - be strict about filtering
- If a user asks for records "with Dayton address", only include rows where an address field contains "Dayton"
- If no records match the filter, clearly state "No records found matching the criteria"

When analyzing data:
- Be precise with numbers and calculations
- Clearly identify which file each piece of data comes from
- If comparing data across files, be explicit about the comparison
- If asked for specific records, show ONLY those records in a clear format (use markdown tables)
- Format monetary values with $ and commas
- Format dates in a readable way
- Use markdown tables for tabular output
PROMPT;

            $userPrompt = <<<PROMPT
Here is CSV data from multiple files:

{$combinedContent}

User question: {$validated['query']}

IMPORTANT: If this is a filtering request, return ONLY the records that match the filter criteria. Do not include non-matching records. Use a markdown table for the results.
PROMPT;

            $response = $this->gemini->generateWithSystem($systemPrompt, $userPrompt, [
                'temperature' => 0.3,
                'max_tokens' => 4096,
            ]);

            if ($response->isError()) {
                return response()->json([
                    'message' => 'Gemini query failed',
                    'error' => $response->getError(),
                ], 500);
            }

            return response()->json([
                'data' => [
                    'file_count' => count($validated['file_ids']),
                    'query' => $validated['query'],
                    'response' => $response->getText(),
                    'tokens_used' => $response->getTotalTokens(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Query multiple CSV failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to query CSV files',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simple chat with Gemini.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'context' => 'nullable|string|max:50000',
        ]);

        $prompt = $validated['message'];

        if (!empty($validated['context'])) {
            $prompt = "Context:\n{$validated['context']}\n\nQuestion: {$validated['message']}";
        }

        $response = $this->gemini->generateText($prompt);

        if ($response->isError()) {
            return response()->json([
                'message' => 'Chat failed',
                'error' => $response->getError(),
            ], 500);
        }

        return response()->json([
            'data' => [
                'response' => $response->getText(),
                'tokens_used' => $response->getTotalTokens(),
            ],
        ]);
    }

    /**
     * Query CSV with Python pre-processing for better performance.
     *
     * This endpoint uses Python/pandas to pre-filter large CSV files
     * before sending the filtered data to Gemini for analysis.
     */
    public function queryCsvFast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => 'required|string',
            'query' => 'required|string|max:2000',
            'filters' => 'nullable|array',
            'filters.*' => 'string',
        ]);

        try {
            // Check if Python is available
            if (!$this->pythonCsv->isAvailable()) {
                Log::warning('GeminiController: Python not available, falling back to standard query');
                // Fall back to standard query
                return $this->queryCsv($request);
            }

            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Get file metadata
            $fileMeta = $driveService->files->get($validated['file_id'], [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            $mimeType = $fileMeta->getMimeType();
            $content = '';

            // Handle Google Sheets - export as CSV
            if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                $response = $driveService->files->export($validated['file_id'], 'text/csv', [
                    'alt' => 'media',
                ]);
                $content = $response->getBody()->getContents();
            } else {
                $response = $driveService->files->get($validated['file_id'], [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                $content = $response->getBody()->getContents();
            }

            $originalSize = strlen($content);
            $filters = $validated['filters'] ?? null;

            // Use Python to pre-process the data
            $pythonResult = $this->pythonCsv->prepareForGemini($content, $filters);

            if ($pythonResult->isError()) {
                Log::warning('GeminiController: Python processing failed, falling back', [
                    'error' => $pythonResult->getError(),
                ]);
                // Fall back to standard query
                return $this->queryCsv($request);
            }

            $processedCsv = $pythonResult->getCsv();
            $processedSize = strlen($processedCsv);

            Log::info('GeminiController: Python pre-processing complete', [
                'original_size' => $originalSize,
                'processed_size' => $processedSize,
                'original_rows' => $pythonResult->raw['original_count'] ?? 0,
                'filtered_rows' => $pythonResult->getRowCount(),
            ]);

            $systemPrompt = <<<PROMPT
You are a data analyst assistant. You help users query and analyze CSV data.

The data has been pre-filtered using Python/pandas for better accuracy.

CRITICAL INSTRUCTIONS:
- The data you receive has already been filtered - analyze ALL rows provided
- Be precise with numbers and calculations
- If asked for specific records, show ALL records in the data using markdown tables
- If asked for summaries or aggregations, calculate them accurately
- Format monetary values with $ and commas
- Format dates in a readable way
- Use markdown tables for tabular output

When no data is provided or the dataset is empty, clearly state that no matching records were found.
PROMPT;

            $userPrompt = <<<PROMPT
Here is pre-filtered CSV data from file "{$fileMeta->getName()}" ({$pythonResult->getRowCount()} rows):

{$processedCsv}

User question: {$validated['query']}

Analyze the data above and respond to the user's question. Use markdown tables to display data.
PROMPT;

            $geminiResponse = $this->gemini->generateWithSystem($systemPrompt, $userPrompt, [
                'temperature' => 0.3,
                'max_tokens' => 4096,
            ]);

            if ($geminiResponse->isError()) {
                return response()->json([
                    'message' => 'Gemini query failed',
                    'error' => $geminiResponse->getError(),
                ], 500);
            }

            return response()->json([
                'data' => [
                    'file_name' => $fileMeta->getName(),
                    'query' => $validated['query'],
                    'response' => $geminiResponse->getText(),
                    'tokens_used' => $geminiResponse->getTotalTokens(),
                    'processing' => [
                        'method' => 'python_pandas',
                        'original_rows' => $pythonResult->raw['original_count'] ?? 0,
                        'filtered_rows' => $pythonResult->getRowCount(),
                        'truncated' => $pythonResult->truncated,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Fast query CSV failed', [
                'file_id' => $validated['file_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to query CSV',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Filter CSV data using Python without Gemini.
     *
     * Returns the filtered data directly, useful for data exploration.
     */
    public function filterCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => 'required|string',
            'filters' => 'required|array',
            'filters.*' => 'string',
        ]);

        try {
            if (!$this->pythonCsv->isAvailable()) {
                return response()->json([
                    'message' => 'Python processor not available',
                ], 503);
            }

            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Get file metadata
            $fileMeta = $driveService->files->get($validated['file_id'], [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            $mimeType = $fileMeta->getMimeType();
            $content = '';

            if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                $response = $driveService->files->export($validated['file_id'], 'text/csv', [
                    'alt' => 'media',
                ]);
                $content = $response->getBody()->getContents();
            } else {
                $response = $driveService->files->get($validated['file_id'], [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                $content = $response->getBody()->getContents();
            }

            $result = $this->pythonCsv->filter($content, $validated['filters']);

            if ($result->isError()) {
                return response()->json([
                    'message' => 'Filter failed',
                    'error' => $result->getError(),
                ], 500);
            }

            return response()->json([
                'data' => [
                    'file_name' => $fileMeta->getName(),
                    'columns' => $result->getColumns(),
                    'rows' => $result->getData(),
                    'row_count' => $result->getRowCount(),
                    'original_count' => $result->originalCount,
                    'truncated' => $result->truncated,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Filter CSV failed', [
                'file_id' => $validated['file_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to filter CSV',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate revenue totals by shop from CSV.
     *
     * Reads a CSV file with 'shop' and 'amount' columns (or similar)
     * and returns totals per shop plus a grand total.
     */
    public function revenueByShop(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => 'required|string',
            'shop_column' => 'nullable|string',
            'amount_column' => 'nullable|string',
        ]);

        try {
            $driveService = $this->googleClient->getImpersonatedDriveService();

            // Get file metadata
            $fileMeta = $driveService->files->get($validated['file_id'], [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            $mimeType = $fileMeta->getMimeType();
            $content = '';

            // Handle Google Sheets - export as CSV
            if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                $response = $driveService->files->export($validated['file_id'], 'text/csv', [
                    'alt' => 'media',
                ]);
                $content = $response->getBody()->getContents();
            } else {
                $response = $driveService->files->get($validated['file_id'], [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                $content = $response->getBody()->getContents();
            }

            // Parse CSV
            $lines = str_getcsv($content, "\n");
            $headers = [];
            $rows = [];

            foreach ($lines as $index => $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $row = str_getcsv($line);

                if ($index === 0) {
                    $headers = array_map('trim', $row);
                } else {
                    if (count($row) === count($headers)) {
                        $rows[] = array_combine($headers, $row);
                    }
                }
            }

            // Auto-detect shop and amount columns if not provided
            $shopColumn = $validated['shop_column'] ?? $this->detectColumn($headers, ['shop', 'location', 'store', 'branch', 'site']);
            $amountColumn = $validated['amount_column'] ?? $this->detectColumn($headers, ['amount', 'total', 'revenue', 'sales', 'value', 'sum']);

            if (!$shopColumn) {
                return response()->json([
                    'message' => 'Could not detect shop column. Available columns: ' . implode(', ', $headers),
                    'headers' => $headers,
                ], 400);
            }

            if (!$amountColumn) {
                return response()->json([
                    'message' => 'Could not detect amount column. Available columns: ' . implode(', ', $headers),
                    'headers' => $headers,
                ], 400);
            }

            // Aggregate by shop
            $shopTotals = [];
            $grandTotal = 0;
            $rowCount = 0;

            foreach ($rows as $row) {
                $shop = trim($row[$shopColumn] ?? 'Unknown');
                $amountRaw = $row[$amountColumn] ?? '0';

                // Clean amount: remove $, commas, and convert to float
                $amount = (float) preg_replace('/[^0-9.\-]/', '', $amountRaw);

                if (!isset($shopTotals[$shop])) {
                    $shopTotals[$shop] = [
                        'shop' => $shop,
                        'total' => 0,
                        'count' => 0,
                    ];
                }

                $shopTotals[$shop]['total'] += $amount;
                $shopTotals[$shop]['count']++;
                $grandTotal += $amount;
                $rowCount++;
            }

            // Sort by total descending
            usort($shopTotals, fn($a, $b) => $b['total'] <=> $a['total']);

            // Format totals
            $formattedShopTotals = array_map(function ($item) {
                return [
                    'shop' => $item['shop'],
                    'total' => $item['total'],
                    'total_formatted' => '$' . number_format($item['total'], 2),
                    'transaction_count' => $item['count'],
                ];
            }, $shopTotals);

            return response()->json([
                'data' => [
                    'file_name' => $fileMeta->getName(),
                    'shop_column' => $shopColumn,
                    'amount_column' => $amountColumn,
                    'shops' => $formattedShopTotals,
                    'grand_total' => $grandTotal,
                    'grand_total_formatted' => '$' . number_format($grandTotal, 2),
                    'total_rows' => $rowCount,
                    'shop_count' => count($shopTotals),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Revenue by shop failed', [
                'file_id' => $validated['file_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to calculate revenue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detect a column name from a list of possible matches.
     */
    private function detectColumn(array $headers, array $possibleNames): ?string
    {
        $headersLower = array_map('strtolower', $headers);

        foreach ($possibleNames as $name) {
            $nameLower = strtolower($name);

            // Exact match
            $index = array_search($nameLower, $headersLower);
            if ($index !== false) {
                return $headers[$index];
            }

            // Partial match (column contains the name)
            foreach ($headersLower as $i => $header) {
                if (str_contains($header, $nameLower)) {
                    return $headers[$i];
                }
            }
        }

        return null;
    }

    /**
     * Get CSV summary statistics using Python.
     */
    public function summarizeCsv(Request $request, string $fileId): JsonResponse
    {
        try {
            if (!$this->pythonCsv->isAvailable()) {
                return response()->json([
                    'message' => 'Python processor not available',
                ], 503);
            }

            $driveService = $this->googleClient->getImpersonatedDriveService();

            $fileMeta = $driveService->files->get($fileId, [
                'fields' => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            $mimeType = $fileMeta->getMimeType();
            $content = '';

            if ($mimeType === 'application/vnd.google-apps.spreadsheet') {
                $response = $driveService->files->export($fileId, 'text/csv', [
                    'alt' => 'media',
                ]);
                $content = $response->getBody()->getContents();
            } else {
                $response = $driveService->files->get($fileId, [
                    'alt' => 'media',
                    'supportsAllDrives' => true,
                ]);
                $content = $response->getBody()->getContents();
            }

            $result = $this->pythonCsv->summary($content);

            if ($result->isError()) {
                return response()->json([
                    'message' => 'Summary failed',
                    'error' => $result->getError(),
                ], 500);
            }

            return response()->json([
                'data' => [
                    'file_name' => $fileMeta->getName(),
                    'row_count' => $result->raw['row_count'] ?? 0,
                    'column_count' => $result->raw['column_count'] ?? 0,
                    'columns' => $result->raw['columns'] ?? [],
                    'sample_data' => $result->raw['sample_data'] ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GeminiController: Summarize CSV failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to summarize CSV',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
