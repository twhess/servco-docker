<?php

namespace App\Services\Google;

use Google\Service\Sheets as Google_Service_Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;

/**
 * Google Sheets service for spreadsheet operations.
 *
 * Provides methods for reading, writing, and managing Google Sheets
 * using a service account.
 *
 * Usage:
 *   $sheets = app(GoogleSheetsService::class);
 *   $response = $sheets->readRange('spreadsheet-id', 'Sheet1!A1:D10');
 */
class GoogleSheetsService
{
    protected Google_Service_Sheets $service;
    protected ?string $defaultSpreadsheetId;

    public function __construct(protected GoogleClientService $clientService)
    {
        $this->service = $clientService->getSheetsService();
        $this->defaultSpreadsheetId = $clientService->getDefaultSpreadsheetId();
    }

    /**
     * Read values from a range.
     *
     * @param string $spreadsheetId The spreadsheet ID (or null to use default)
     * @param string $range The A1 notation range (e.g., 'Sheet1!A1:D10')
     */
    public function readRange(?string $spreadsheetId, string $range): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        $spreadsheetId = $spreadsheetId ?? $this->defaultSpreadsheetId;

        if (!$spreadsheetId) {
            return GoogleResponse::error('Spreadsheet ID is required');
        }

        try {
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues() ?? [];

            return GoogleResponse::success($values, [
                'range' => $response->getRange(),
                'major_dimension' => $response->getMajorDimension(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Read failed', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Write values to a range (overwrites existing data).
     *
     * @param string $spreadsheetId The spreadsheet ID (or null to use default)
     * @param string $range The A1 notation range
     * @param array $values 2D array of values
     */
    public function writeRange(?string $spreadsheetId, string $range, array $values): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        $spreadsheetId = $spreadsheetId ?? $this->defaultSpreadsheetId;

        if (!$spreadsheetId) {
            return GoogleResponse::error('Spreadsheet ID is required');
        }

        try {
            $body = new ValueRange([
                'values' => $values,
            ]);

            $params = [
                'valueInputOption' => 'USER_ENTERED', // Parse values like the UI would
            ];

            $result = $this->service->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $body,
                $params
            );

            Log::info('GoogleSheetsService: Values written', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'updated_cells' => $result->getUpdatedCells(),
            ]);

            return GoogleResponse::success([
                'updated_range' => $result->getUpdatedRange(),
                'updated_rows' => $result->getUpdatedRows(),
                'updated_columns' => $result->getUpdatedColumns(),
                'updated_cells' => $result->getUpdatedCells(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Write failed', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Append rows to a sheet (adds after last row with data).
     *
     * @param string $spreadsheetId The spreadsheet ID (or null to use default)
     * @param string $range The range to append to (e.g., 'Sheet1' or 'Sheet1!A:Z')
     * @param array $rows Array of row arrays
     */
    public function appendRows(?string $spreadsheetId, string $range, array $rows): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        $spreadsheetId = $spreadsheetId ?? $this->defaultSpreadsheetId;

        if (!$spreadsheetId) {
            return GoogleResponse::error('Spreadsheet ID is required');
        }

        try {
            $body = new ValueRange([
                'values' => $rows,
            ]);

            $params = [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ];

            $result = $this->service->spreadsheets_values->append(
                $spreadsheetId,
                $range,
                $body,
                $params
            );

            Log::info('GoogleSheetsService: Rows appended', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'updated_rows' => $result->getUpdates()->getUpdatedRows(),
            ]);

            return GoogleResponse::success([
                'updated_range' => $result->getUpdates()->getUpdatedRange(),
                'updated_rows' => $result->getUpdates()->getUpdatedRows(),
                'updated_cells' => $result->getUpdates()->getUpdatedCells(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Append failed', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Clear values in a range.
     */
    public function clearRange(?string $spreadsheetId, string $range): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        $spreadsheetId = $spreadsheetId ?? $this->defaultSpreadsheetId;

        if (!$spreadsheetId) {
            return GoogleResponse::error('Spreadsheet ID is required');
        }

        try {
            $result = $this->service->spreadsheets_values->clear(
                $spreadsheetId,
                $range,
                new \Google\Service\Sheets\ClearValuesRequest()
            );

            return GoogleResponse::success([
                'cleared_range' => $result->getClearedRange(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Clear failed', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Get spreadsheet metadata.
     */
    public function getSpreadsheetInfo(?string $spreadsheetId): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        $spreadsheetId = $spreadsheetId ?? $this->defaultSpreadsheetId;

        if (!$spreadsheetId) {
            return GoogleResponse::error('Spreadsheet ID is required');
        }

        try {
            $spreadsheet = $this->service->spreadsheets->get($spreadsheetId);

            $sheets = [];
            foreach ($spreadsheet->getSheets() as $sheet) {
                $props = $sheet->getProperties();
                $sheets[] = [
                    'sheet_id' => $props->getSheetId(),
                    'title' => $props->getTitle(),
                    'index' => $props->getIndex(),
                    'row_count' => $props->getGridProperties()->getRowCount(),
                    'column_count' => $props->getGridProperties()->getColumnCount(),
                ];
            }

            return GoogleResponse::success([
                'spreadsheet_id' => $spreadsheet->getSpreadsheetId(),
                'title' => $spreadsheet->getProperties()->getTitle(),
                'locale' => $spreadsheet->getProperties()->getLocale(),
                'url' => $spreadsheet->getSpreadsheetUrl(),
                'sheets' => $sheets,
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Get info failed', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Create a new spreadsheet.
     */
    public function createSpreadsheet(string $title): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        try {
            $spreadsheet = new Spreadsheet([
                'properties' => new SpreadsheetProperties([
                    'title' => $title,
                ]),
            ]);

            $result = $this->service->spreadsheets->create($spreadsheet);

            Log::info('GoogleSheetsService: Spreadsheet created', [
                'spreadsheet_id' => $result->getSpreadsheetId(),
                'title' => $title,
            ]);

            return GoogleResponse::success([
                'spreadsheet_id' => $result->getSpreadsheetId(),
                'title' => $result->getProperties()->getTitle(),
                'url' => $result->getSpreadsheetUrl(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Create failed', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Batch update (for advanced operations like formatting, adding sheets, etc.).
     */
    public function batchUpdate(?string $spreadsheetId, array $requests): GoogleResponse
    {
        if (!$this->clientService->isConfigured()) {
            return GoogleResponse::error('Google Sheets not configured');
        }

        $spreadsheetId = $spreadsheetId ?? $this->defaultSpreadsheetId;

        if (!$spreadsheetId) {
            return GoogleResponse::error('Spreadsheet ID is required');
        }

        try {
            $batchRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => $requests,
            ]);

            $result = $this->service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);

            return GoogleResponse::success([
                'spreadsheet_id' => $result->getSpreadsheetId(),
                'replies' => $result->getReplies(),
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleSheetsService: Batch update failed', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $spreadsheetId,
            ]);

            return GoogleResponse::error($e->getMessage());
        }
    }

    /**
     * Read a single cell value.
     */
    public function readCell(?string $spreadsheetId, string $cell): GoogleResponse
    {
        $response = $this->readRange($spreadsheetId, $cell);

        if ($response->isError()) {
            return $response;
        }

        $data = $response->getData();
        $value = $data[0][0] ?? null;

        return GoogleResponse::success(['value' => $value]);
    }

    /**
     * Write a single cell value.
     */
    public function writeCell(?string $spreadsheetId, string $cell, mixed $value): GoogleResponse
    {
        return $this->writeRange($spreadsheetId, $cell, [[$value]]);
    }

    /**
     * Find rows matching a value in a specific column.
     */
    public function findRows(?string $spreadsheetId, string $sheetName, int $searchColumn, mixed $searchValue): GoogleResponse
    {
        // Read all data from the sheet
        $response = $this->readRange($spreadsheetId, $sheetName);

        if ($response->isError()) {
            return $response;
        }

        $rows = $response->getData();
        $matchingRows = [];

        foreach ($rows as $index => $row) {
            if (isset($row[$searchColumn]) && $row[$searchColumn] == $searchValue) {
                $matchingRows[] = [
                    'row_index' => $index,
                    'row_number' => $index + 1, // 1-based for Sheets
                    'data' => $row,
                ];
            }
        }

        return GoogleResponse::success($matchingRows);
    }

    /**
     * Check if service is configured.
     */
    public function isConfigured(): bool
    {
        return $this->clientService->isConfigured();
    }
}
