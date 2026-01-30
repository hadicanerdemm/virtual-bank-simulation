<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;

/**
 * Exchange Rate Service
 */
class ExchangeRateService
{
    private Database $db;
    private array $rates = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadRates();
    }

    /**
     * Load rates from database
     */
    private function loadRates(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM exchange_rates WHERE is_active = 1"
        );

        foreach ($rows as $row) {
            $key = $row['base_currency'] . '_' . $row['target_currency'];
            $this->rates[$key] = (float) $row['rate'];
        }
    }

    /**
     * Get exchange rate
     */
    public function getRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $key = $from . '_' . $to;
        
        if (isset($this->rates[$key])) {
            return $this->rates[$key];
        }

        // Try reverse rate
        $reverseKey = $to . '_' . $from;
        if (isset($this->rates[$reverseKey])) {
            return 1 / $this->rates[$reverseKey];
        }

        // Default rates if not in DB
        $defaults = [
            'USD_TRY' => 32.50,
            'EUR_TRY' => 35.20,
            'USD_EUR' => 0.92,
            'EUR_USD' => 1.09,
            'TRY_USD' => 0.031,
            'TRY_EUR' => 0.028
        ];

        return $defaults[$key] ?? 1.0;
    }

    /**
     * Convert amount
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $rate = $this->getRate($from, $to);
        return round($amount * $rate, 2);
    }

    /**
     * Get all rates
     */
    public function getAllRates(): array
    {
        return [
            'USD_TRY' => $this->getRate('USD', 'TRY'),
            'EUR_TRY' => $this->getRate('EUR', 'TRY'),
            'TRY_USD' => $this->getRate('TRY', 'USD'),
            'TRY_EUR' => $this->getRate('TRY', 'EUR'),
            'USD_EUR' => $this->getRate('USD', 'EUR'),
            'EUR_USD' => $this->getRate('EUR', 'USD'),
        ];
    }

    /**
     * Update rate
     */
    public function updateRate(string $from, string $to, float $rate): bool
    {
        // Deactivate old rate
        $this->db->query(
            "UPDATE exchange_rates SET is_active = 0 WHERE base_currency = ? AND target_currency = ?",
            [$from, $to]
        );

        // Insert new rate
        $this->db->query(
            "INSERT INTO exchange_rates (id, base_currency, target_currency, rate, is_active) VALUES (?, ?, ?, ?, 1)",
            [Database::generateUUID(), $from, $to, $rate]
        );

        // Refresh cache
        $this->loadRates();

        return true;
    }

    /**
     * Format rate display
     */
    public function formatRate(string $from, string $to): string
    {
        $rate = $this->getRate($from, $to);
        $symbol = match($to) {
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            default => $to
        };

        return "1 {$from} = {$symbol}" . number_format($rate, 4);
    }
}
