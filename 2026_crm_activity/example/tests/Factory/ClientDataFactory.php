<?php

declare(strict_types=1);

namespace Tests\Factory;

final class ClientDataFactory
{
    public static function lowRiskClient(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Test Firma Sp. z o.o.',
            'nip' => '5261234567',
            'contact_email' => 'test@firma.pl',
            'annual_revenue' => 500_000,
        ], $overrides);
    }

    public static function mediumRiskClient(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Średnia Korporacja S.A.',
            'nip' => '7891234567',
            'contact_email' => 'cfo@srednia.pl',
            'annual_revenue' => 5_000_000,
        ], $overrides);
    }

    public static function flaggedNipClient(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Podejrzana Sp. z o.o.',
            'nip' => '9961234567',
            'contact_email' => 'info@podejrzana.pl',
            'annual_revenue' => 200_000,
        ], $overrides);
    }

    public static function unknownNipClient(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Ghost Company Ltd.',
            'nip' => '0061234567',
            'contact_email' => 'ghost@nowhere.com',
            'annual_revenue' => 100_000,
        ], $overrides);
    }

    public static function simplifiedPartner(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Partner Fintech Sp. z o.o.',
            'nip' => '1234567890',
            'contact_email' => 'partner@fintech.pl',
            'partner_referral_code' => 'REF-001',
            'annual_revenue' => 300_000,
        ], $overrides);
    }
}
