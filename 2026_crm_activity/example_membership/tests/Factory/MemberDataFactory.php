<?php

declare(strict_types=1);

namespace Tests\Factory;

final class MemberDataFactory
{
    public static function standardPurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-001',
            'transaction_id' => 'TXN-12345',
            'amount' => 150.00,
            'currency' => 'PLN',
            'store_id' => 'STORE-WAW-01',
            'total_points' => 3_000,
            'current_tier' => 'bronze',
        ], $overrides);
    }

    public static function highValuePurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-002',
            'transaction_id' => 'TXN-67890',
            'amount' => 350.00,
            'currency' => 'PLN',
            'store_id' => 'STORE-KRK-01',
            'total_points' => 8_000,
            'current_tier' => 'silver',
        ], $overrides);
    }

    public static function tierUpgradePurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-003',
            'transaction_id' => 'TXN-11111',
            'amount' => 500.00,
            'currency' => 'PLN',
            'store_id' => 'STORE-GDA-01',
            'total_points' => 10_500,
            'current_tier' => 'silver',
        ], $overrides);
    }

    public static function fraudSuspectedPurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-004',
            'transaction_id' => 'TXN-99999',
            'amount' => 8_000.00,
            'currency' => 'PLN',
            'store_id' => 'STORE-WAW-02',
            'total_points' => 1_000,
            'current_tier' => 'bronze',
        ], $overrides);
    }

    public static function invalidPurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-005',
            'transaction_id' => 'TXN-00000',
            'amount' => 0,
            'currency' => 'PLN',
            'store_id' => 'STORE-WAW-01',
            'total_points' => 500,
            'current_tier' => 'bronze',
        ], $overrides);
    }

    public static function onlinePurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-010',
            'transaction_id' => 'TXN-ONLINE-001',
            'order_id' => 'ORD-2026-001',
            'amount' => 200.00,
            'currency' => 'PLN',
            'total_points' => 5_000,
            'current_tier' => 'silver',
        ], $overrides);
    }

    public static function onlineTierUpgradePurchase(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-011',
            'transaction_id' => 'TXN-ONLINE-002',
            'order_id' => 'ORD-2026-002',
            'amount' => 300.00,
            'currency' => 'PLN',
            'total_points' => 12_000,
            'current_tier' => 'gold',
        ], $overrides);
    }

    public static function validReferral(array $overrides = []): array
    {
        return array_merge([
            'referrer_member_id' => 'MBR-020',
            'referred_member_id' => 'MBR-021',
            'referral_code' => 'REF-ABC123',
        ], $overrides);
    }

    public static function duplicateReferral(array $overrides = []): array
    {
        return array_merge([
            'referrer_member_id' => 'MBR-020',
            'referred_member_id' => 'MBR-022',
            'referral_code' => 'DUP-EXISTING',
        ], $overrides);
    }

    public static function invalidReferral(array $overrides = []): array
    {
        return array_merge([
            'referrer_member_id' => 'MBR-030',
            'referred_member_id' => 'MBR-031',
            'referral_code' => 'INV-EXPIRED',
        ], $overrides);
    }

    public static function rewardRedemption(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-100',
            'reward_id' => 'RWD-10PCT',
            'reward_points_cost' => 1_000,
        ], $overrides);
    }

    public static function expensiveRewardRedemption(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-101',
            'reward_id' => 'RWD-VIP',
            'reward_points_cost' => 15_000,
        ], $overrides);
    }

    public static function unavailableRewardRedemption(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-102',
            'reward_id' => 'RWD-NONEXISTENT',
            'reward_points_cost' => 100,
        ], $overrides);
    }

    public static function inactiveRewardRedemption(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 'MBR-103',
            'reward_id' => 'RWD-INACTIVE',
            'reward_points_cost' => 100,
        ], $overrides);
    }
}
