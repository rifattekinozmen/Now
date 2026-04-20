<?php

namespace App\Services\Notification;

use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp / SMS outbound notification service.
 *
 * Supported providers (config/notifications.php → provider):
 *   - 'null'   — no-op, logs only (default)
 *   - 'twilio' — Twilio WhatsApp API
 *
 * Per-tenant token: TenantSetting key 'whatsapp_token' (secret).
 */
final class WhatsAppNotificationService
{
    public function isAvailable(): bool
    {
        $provider = config('notifications.provider', 'null');

        if ($provider === 'twilio') {
            return filled(config('notifications.twilio.account_sid'))
                && filled(config('notifications.twilio.auth_token'))
                && filled(config('notifications.twilio.from_number'));
        }

        return false;
    }

    /**
     * Send a WhatsApp message to the given phone number.
     *
     * @return bool Whether the message was sent (false if provider unavailable)
     */
    public function send(string $to, string $message, ?int $tenantId = null): bool
    {
        $provider = config('notifications.provider', 'null');

        Log::info('WhatsAppNotificationService: send', [
            'provider' => $provider,
            'to' => $to,
            'message' => $message,
        ]);

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($to, $message, $tenantId),
            default => false, // null provider: no-op
        };
    }

    /**
     * Build the standard shipment-dispatched message.
     */
    public function buildDispatchMessage(
        string $customerName,
        string $vehiclePlate,
        string $trackingUrl
    ): string {
        return __('Dear :name, your shipment is on the way. Vehicle: :plate. Track: :url', [
            'name' => $customerName,
            'plate' => $vehiclePlate,
            'url' => $trackingUrl,
        ]);
    }

    private function sendViaTwilio(string $to, string $message, ?int $tenantId = null): bool
    {
        $sid = config('notifications.twilio.account_sid');
        $token = config('notifications.twilio.auth_token');
        $from = config('notifications.twilio.from_number');

        // Per-tenant token override
        if ($tenantId !== null) {
            $perTenantToken = TenantSetting::get($tenantId, 'whatsapp_token');
            if (filled($perTenantToken)) {
                $token = $perTenantToken;
            }
        }

        if (! filled($sid) || ! filled($token) || ! filled($from)) {
            Log::warning('WhatsAppNotificationService: Twilio credentials not configured');

            return false;
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => 'whatsapp:'.$from,
                    'To' => 'whatsapp:'.$to,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WhatsAppNotificationService: Twilio error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppNotificationService: Exception', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
