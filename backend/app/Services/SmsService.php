<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SMS Service for sending text messages via Twilio.
 *
 * This is a stub implementation that logs messages if Twilio is not configured.
 * To enable SMS:
 * 1. Add Twilio SDK: composer require twilio/sdk
 * 2. Set env vars: TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM
 */
class SmsService
{
    protected ?string $sid;
    protected ?string $token;
    protected ?string $from;
    protected bool $configured = false;

    public function __construct()
    {
        $this->sid = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->from = config('services.twilio.from');

        $this->configured = !empty($this->sid) && !empty($this->token) && !empty($this->from);
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Send an SMS message.
     *
     * @param string $to Phone number in E.164 format (e.g., +15551234567)
     * @param string $message The message content
     * @return bool True if sent successfully
     */
    public function send(string $to, string $message): bool
    {
        if (!$this->configured) {
            Log::warning('SmsService: Twilio not configured, SMS not sent', [
                'to' => $to,
                'message' => substr($message, 0, 50) . '...',
            ]);
            return false;
        }

        // Check if Twilio SDK is installed
        if (!class_exists(\Twilio\Rest\Client::class)) {
            Log::warning('SmsService: Twilio SDK not installed. Run: composer require twilio/sdk', [
                'to' => $to,
            ]);
            return false;
        }

        try {
            $client = new \Twilio\Rest\Client($this->sid, $this->token);

            $client->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);

            Log::info('SmsService: Message sent', [
                'to' => $to,
                'length' => strlen($message),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('SmsService: Failed to send SMS', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate a phone number format (E.164).
     *
     * @param string $phone Phone number to validate
     * @return bool True if valid E.164 format
     */
    public function isValidE164(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    /**
     * Format a US phone number to E.164.
     *
     * @param string $phone 10-digit US phone number
     * @return string|null E.164 formatted number or null if invalid
     */
    public function formatToE164(string $phone): ?string
    {
        // Remove all non-digits
        $digits = preg_replace('/\D/', '', $phone);

        // US phone number (10 digits)
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        // Already has country code (11 digits starting with 1)
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }

        return null;
    }
}
