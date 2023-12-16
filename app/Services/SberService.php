<?php

namespace App\Services;

class SberService
{
    const SBER_3516_ACCOUNT_ID = 494593;
    const SBER_5430_ACCOUNT_ID = 494594;

    public static function getAccountId(string $cardNumber): int
    {
        return match(substr($cardNumber, -4, 4)) {
            '3516','0459' => self::SBER_3516_ACCOUNT_ID,
            '5430' => self::SBER_5430_ACCOUNT_ID,
            default => 0
        };
    }

    public static function getAccountTitle(int $accountId): string
    {
        return match($accountId) {
            self::SBER_3516_ACCOUNT_ID => 'СБ Основная карта - 0459 (3516)',
            self::SBER_5430_ACCOUNT_ID => 'СБ Кредитная карта Visa Gold 5430',
            default => ''
        };
    }
}
