<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class PaynowGateway
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly StructuredLogger $logger,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('paynow.enabled')
            && filled(config('paynow.integration_id'))
            && filled(config('paynow.integration_key'));
    }

    public function readyFor(string $currencyCode): bool
    {
        $parts = parse_url((string) config('app.url'));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $this->enabled()
            && $currencyCode === config('paynow.currency')
            && ($parts['scheme'] ?? '') === 'https'
            && $host !== ''
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public function start(Reservation $reservation, string $customerToken): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        if (! $this->readyFor($reservation->currency_code)) {
            throw new ServiceUnavailableHttpException(null, 'Paynow requires a configured merchant account, matching currency, and public HTTPS APP_URL.');
        }

        $payment = DB::transaction(function () use ($reservation): ReservationPayment {
            $current = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($current->status !== Reservation::STATUS_AWAITING_PAYMENT) {
                throw new ConflictHttpException('This reservation is no longer awaiting payment.');
            }
            $payment = ReservationPayment::query()->where('reservation_id', $current->id)->lockForUpdate()->first();
            if ($payment) {
                return $payment;
            }

            return ReservationPayment::create([
                'reservation_id' => $current->id,
                'provider' => 'paynow',
                'merchant_reference' => (string) $current->id,
                'amount_minor' => $current->amount_minor,
                'currency_code' => $current->currency_code,
                'status' => 'initiating',
            ]);
        }, 3);

        if ($payment->redirect_url) {
            return $payment->redirect_url;
        }
        if (! in_array($payment->status, ['initiating', 'failed', 'initiation_unknown'], true)) {
            return null;
        }

        $values = [
            'id' => (string) config('paynow.integration_id'),
            'reference' => $payment->merchant_reference,
            'amount' => $this->amount($payment->amount_minor),
            'additionalinfo' => 'Taziva Motor Spares reservation '.$reservation->reservation_number,
            'returnurl' => route('reservation.paynow-return', $customerToken),
            'resulturl' => route('paynow.result'),
            'status' => 'Message',
        ];
        $values['hash'] = PaynowHash::make($values, (string) config('paynow.integration_key'));

        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('paynow.connect_timeout_seconds', 3))
                ->timeout((int) config('paynow.timeout_seconds', 8))
                ->post((string) config('paynow.initiate_url'), $values);
        } catch (ConnectionException $exception) {
            report($exception);
            $payment->update(['status' => 'initiation_unknown']);

            return null;
        }

        parse_str($response->body(), $reply);
        $reply = $this->lowercaseKeys($reply);
        if (! $response->successful() || ! PaynowHash::valid($reply, (string) config('paynow.integration_key'))) {
            $payment->update(['status' => 'failed', 'provider_status' => 'invalid_or_failed_initiation']);

            return null;
        }

        if (strtolower((string) ($reply['status'] ?? '')) !== 'ok'
            || ! $this->validPaynowUrl((string) ($reply['browserurl'] ?? ''), false)
            || ! $this->validPaynowUrl((string) ($reply['pollurl'] ?? ''), true)) {
            $payment->update(['status' => 'failed', 'provider_status' => 'rejected_initiation']);

            return null;
        }

        $payment->update([
            'status' => 'pending',
            'provider_reference' => isset($reply['paynowreference']) ? (string) $reply['paynowreference'] : null,
            'poll_url' => (string) $reply['pollurl'],
            'redirect_url' => (string) $reply['browserurl'],
            'provider_status' => 'Created',
        ]);
        app(AuditLogger::class)->log('reservation.paynow.initiated', $payment, null, [
            'reservation_id' => $reservation->id,
            'merchant_reference' => $payment->merchant_reference,
            'amount_minor' => $payment->amount_minor,
            'currency_code' => $payment->currency_code,
            'status' => 'pending',
        ], $reservation->branch_id);

        return $payment->refresh()->redirect_url;
    }

    /** @param array<string, mixed> $callback */
    public function handleCallback(array $callback): void
    {
        $callback = $this->lowercaseKeys($callback);
        $key = (string) config('paynow.integration_key');
        if (! $this->enabled() || ! PaynowHash::valid($callback, $key)) {
            $this->rejectCallback('invalid_signature', 'The Paynow callback signature is invalid.');
        }

        $reference = (string) ($callback['reference'] ?? '');
        $payment = ReservationPayment::query()->where('provider', 'paynow')->where('merchant_reference', $reference)->firstOrFail();
        $eventKey = $this->callbackEventKey($callback, $reference);
        if ($this->eventProcessed($eventKey)) {
            $this->logger->info('paynow.webhook.replay_ignored', [
                'reference' => $reference,
                'event_key' => substr($eventKey, 0, 12),
            ]);

            return;
        }
        if (! $this->matchesAmount((string) ($callback['amount'] ?? ''), $payment->amount_minor)) {
            $this->rejectCallback('amount_mismatch', 'The Paynow callback amount does not match the reservation.');
        }

        $pollUrl = (string) ($payment->poll_url ?: ($callback['pollurl'] ?? ''));
        if (! $this->validPaynowUrl($pollUrl, true)) {
            $this->rejectCallback('untrusted_poll_url', 'No trusted Paynow poll URL is available for this payment.');
        }
        if (! $payment->poll_url) {
            $payment->update(['poll_url' => $pollUrl]);
        }

        $status = $this->poll($pollUrl);
        $this->verifyPaymentMatch($status, $payment);
        $this->applyProviderStatus($payment, $status);
        $this->recordCallback($eventKey, $reference);
    }

    public function refresh(Reservation $reservation): void
    {
        $payment = $reservation->payment;
        if (! $payment?->poll_url || ! in_array($payment->status, ['pending', 'paid_late'], true)) {
            return;
        }
        if (! $this->validPaynowUrl((string) $payment->poll_url, true)) {
            return;
        }

        $status = $this->poll((string) $payment->poll_url);
        $this->verifyPaymentMatch($status, $payment);
        $this->applyProviderStatus($payment, $status);
    }

    /** @return array<string, string> */
    private function poll(string $pollUrl): array
    {
        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('paynow.connect_timeout_seconds', 3))
                ->timeout((int) config('paynow.timeout_seconds', 8))
                ->retry(1 + (int) config('paynow.poll_retries', 2), 200, throw: false)
                ->post($pollUrl, []);
        } catch (ConnectionException $exception) {
            report($exception);
            throw new ServiceUnavailableHttpException(null, 'Paynow status is temporarily unavailable.');
        }
        parse_str($response->body(), $reply);
        $reply = $this->lowercaseKeys($reply);
        if (! $response->successful() || ! PaynowHash::valid($reply, (string) config('paynow.integration_key'))) {
            throw new ConflictHttpException('The Paynow status response could not be authenticated.');
        }

        return $reply;
    }

    /** @param array<string, string> $status */
    private function verifyPaymentMatch(array $status, ReservationPayment $payment): void
    {
        if (($status['reference'] ?? null) !== $payment->merchant_reference
            || ! $this->matchesAmount((string) ($status['amount'] ?? ''), $payment->amount_minor)) {
            throw new ConflictHttpException('The Paynow status does not match this reservation payment.');
        }
    }

    /** @param array<string, string> $status */
    private function applyProviderStatus(ReservationPayment $payment, array $status): void
    {
        $providerStatus = trim((string) ($status['status'] ?? ''));
        $normalized = strtolower($providerStatus);
        $payment = DB::transaction(function () use ($payment, $providerStatus, $normalized, $status): ReservationPayment {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($payment->reservation_id);
            $current = ReservationPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $oldStatus = $current->status;
            $paidStatuses = ['paid', 'awaiting delivery', 'delivered'];

            if ($oldStatus === 'refunded') {
                return $current;
            }

            if (in_array($oldStatus, ['paid', 'paid_late'], true) && in_array($normalized, $paidStatuses, true)) {
                return $current;
            }

            // Paynow events may arrive more than once or out of order. Never downgrade a terminal result.
            if (in_array($oldStatus, ['paid', 'refunded'], true) && in_array($normalized, ['created', 'sent', 'cancelled'], true)) {
                return $current;
            }

            if (in_array($normalized, $paidStatuses, true)) {
                $confirmed = $this->reservations->confirmPayment($reservation);
                $newStatus = $confirmed ? 'paid' : 'paid_late';
                $paynowReference = $status['paynowreference'] ?? $current->provider_reference;
                $current->update([
                    'status' => $newStatus,
                    'provider_status' => $providerStatus,
                    'provider_reference' => $paynowReference,
                    'paid_at' => $current->paid_at ?? now(),
                    'last_event_at' => now(),
                ]);
                if (! $confirmed) {
                    app(AuditLogger::class)->log('reservation.payment.late_review', $reservation, null, [
                        'reservation_number' => $reservation->reservation_number,
                        'provider_reference' => $paynowReference,
                    ], $reservation->branch_id);
                }
            } elseif ($normalized === 'cancelled') {
                if ($oldStatus !== 'paid_late') {
                    $current->update(['status' => 'cancelled', 'provider_status' => $providerStatus, 'last_event_at' => now()]);
                    $this->reservations->cancelAfterPaymentFailure($reservation);
                }
            } elseif ($normalized === 'refunded') {
                $current->update(['status' => 'refunded', 'provider_status' => $providerStatus, 'last_event_at' => now()]);
                $this->reservations->markRefunded($reservation);
            } elseif ($normalized === 'disputed') {
                if ($oldStatus !== 'refunded') {
                    $current->update(['status' => 'disputed', 'provider_status' => $providerStatus, 'last_event_at' => now()]);
                }
            } elseif (! in_array($oldStatus, ['paid', 'paid_late', 'cancelled', 'refunded', 'disputed'], true)) {
                $current->update(['status' => 'pending', 'provider_status' => $providerStatus, 'last_event_at' => now()]);
            }

            if ($current->wasChanged(['status', 'provider_status'])) {
                app(AuditLogger::class)->log('reservation.paynow.status', $current, [
                    'status' => $oldStatus,
                ], [
                    'status' => $current->status,
                    'provider_status' => $current->provider_status,
                    'merchant_reference' => $current->merchant_reference,
                ], $reservation->branch_id);
            }

            return $current->refresh();
        }, 3);

        if ($payment->status === 'paid' && $payment->reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw new ConflictHttpException('The reservation could not be confirmed; payment is marked for manual review.');
        }
    }

    /** @param array<string, string> $callback */
    private function callbackEventKey(array $callback, string $reference): string
    {
        return hash('sha256', implode('|', [
            $reference,
            $callback['paynowreference'] ?? '',
            $callback['amount'] ?? '',
            strtolower($callback['status'] ?? ''),
            $callback['pollurl'] ?? '',
        ]));
    }

    private function eventProcessed(string $eventKey): bool
    {
        return WebhookEvent::query()->where('provider', 'paynow')->where('event_key', $eventKey)->exists();
    }

    private function recordCallback(string $eventKey, string $reference): void
    {
        try {
            WebhookEvent::query()->create([
                'provider' => 'paynow',
                'event_key' => $eventKey,
                'reference' => $reference,
                'received_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->eventProcessed($eventKey)) {
                throw $exception;
            }
            $this->logger->info('paynow.webhook.concurrent_replay_ignored', ['reference' => $reference]);
        }
    }

    private function rejectCallback(string $reason, string $message): never
    {
        $this->logger->warning('paynow.webhook.rejected', ['reason' => $reason]);

        throw new ConflictHttpException($message);
    }

    /** @param array<string, mixed> $values @return array<string, string> */
    private function lowercaseKeys(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[strtolower((string) $key)] = is_scalar($value) ? (string) $value : '';
        }

        return $normalized;
    }

    private function validPaynowUrl(string $url, bool $poll): bool
    {
        if ($url === '' || strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host !== 'paynow.co.zw' && ! str_ends_with($host, '.paynow.co.zw')) {
            return false;
        }
        if ($poll && rtrim(strtolower((string) ($parts['path'] ?? '')), '/') !== '/interface/checkpayment') {
            return false;
        }

        return ! isset($parts['user']) && ! isset($parts['pass']);
    }

    private function amount(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function matchesAmount(string $amount, int $expectedMinor): bool
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) || ! function_exists('bccomp')) {
            return false;
        }
        $expected = $this->amount($expectedMinor);

        return bccomp($amount, $expected, 2) === 0;
    }
}
