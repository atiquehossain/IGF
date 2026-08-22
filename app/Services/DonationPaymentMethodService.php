<?php

namespace App\Services;

class DonationPaymentMethodService
{
    private const DEFINITIONS = [
        'bkash' => [
            'label' => 'bKash',
            'description' => 'Pay securely from your bKash account.',
            'logos' => [
                ['src' => '/image/payment-methods/bkash-reference.svg'],
            ],
        ],
        'nagad' => [
            'label' => 'Nagad',
            'description' => 'Pay securely from your Nagad account.',
            'logos' => [
                ['src' => '/image/payment-methods/nagad.png'],
            ],
        ],
        'card' => [
            'label' => 'Card',
            'description' => 'Pay securely with an eligible Visa or American Express card.',
            'networks' => ['Visa', 'American Express'],
            'logos' => [
                ['src' => '/image/payment-methods/visa-reference.svg'],
                ['src' => '/image/payment-methods/amex.png'],
            ],
        ],
    ];

    public function __construct(private SiteSettingService $siteSettings)
    {
    }

    /**
     * Return public, display-safe options. Gateway identifiers never leave the
     * server, including for unavailable methods.
     */
    public function publicOptions(?string $locale = null): array
    {
        $settings = $this->siteSettings->values($locale ?? app()->getLocale(), true)['donation_page'] ?? [];

        return collect(self::DEFINITIONS)
            ->map(function (array $definition, string $key) use ($settings): array {
                $adminEnabled = filter_var(
                    $settings['enable_' . $key] ?? true,
                    FILTER_VALIDATE_BOOLEAN
                );
                $available = $adminEnabled && $this->isOperationallyReady($key);

                $reason = null;
                if (!$available) {
                    $reason = (string) ($settings['payment_method_unavailable_label'] ?? 'Currently unavailable');
                }

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => (string) ($settings[$key . '_description'] ?? $definition['description']),
                    'networks' => $definition['networks'] ?? null,
                    'logos' => $definition['logos'],
                    'enabled' => $adminEnabled,
                    'available' => $available,
                    'unavailable_reason' => $reason,
                ];
            })
            ->values()
            ->all();
    }

    public function publicKeys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * Return the safe, read-only operational state used by public checkout,
     * customizer validation and the administrator status panel. Credentials
     * and gateway identifiers are never included in this contract.
     */
    public function operationalReadiness(string $key): array
    {
        $definition = self::DEFINITIONS[$key] ?? null;

        if (!is_array($definition)) {
            return [
                'key' => $key,
                'label' => 'Unknown payment method',
                'ready' => false,
                'status' => 'Not ready',
                'message' => 'This payment method is not registered.',
            ];
        }

        $configuration = $this->configuration($key);
        $serverEnabled = filter_var(
            $configuration['enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $gatewayFilter = trim((string) ($configuration['gateway_filter'] ?? ''));
        $credentialsReady = trim((string) config('sslcommerz.store_id')) !== ''
            && trim((string) config('sslcommerz.store_password')) !== '';

        if (!$serverEnabled) {
            $message = 'This channel is disabled in the protected payment configuration.';
        } elseif (!$this->isValidGatewayFilter($gatewayFilter)) {
            $message = 'This channel is awaiting a valid protected provider activation key.';
        } elseif (!$credentialsReady) {
            $message = 'SSLCommerz account credentials have not been configured by the payment administrator.';
        } else {
            $message = 'The payment account and this channel are configured.';
        }

        $ready = $serverEnabled
            && $this->isValidGatewayFilter($gatewayFilter)
            && $credentialsReady;

        return [
            'key' => $key,
            'label' => $definition['label'],
            'ready' => $ready,
            'status' => $ready ? 'Ready' : 'Not ready',
            'message' => $message,
        ];
    }

    public function operationalStatuses(): array
    {
        return collect($this->publicKeys())
            ->map(fn (string $key): array => $this->operationalReadiness($key))
            ->all();
    }

    public function isOperationallyReady(string $key): bool
    {
        return (bool) ($this->operationalReadiness($key)['ready'] ?? false);
    }

    /**
     * Resolve a submitted stable key to protected operational configuration.
     */
    public function resolveAvailable(string $key, ?string $locale = null): ?array
    {
        $option = collect($this->publicOptions($locale))->firstWhere('key', $key);

        if (!$option || !($option['available'] ?? false)) {
            return null;
        }

        $configuration = $this->configuration($key);

        return [
            'key' => $key,
            'gateway_filter' => trim((string) $configuration['gateway_filter']),
        ];
    }

    private function configuration(string $key): array
    {
        $configuration = config('sslcommerz.payment_methods.' . $key, []);

        return is_array($configuration) ? $configuration : [];
    }

    private function isValidGatewayFilter(string $filter): bool
    {
        return $filter !== ''
            && strlen($filter) <= 30
            && preg_match('/^[a-z0-9_]+(?:,[a-z0-9_]+)*$/', $filter) === 1;
    }
}
