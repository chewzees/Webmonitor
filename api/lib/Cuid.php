<?php
declare(strict_types=1);

final class Cuid
{
    private static int $counter = 0;

    /** Generate a cuid-like id (≤30 chars). */
    public static function generate(): string
    {
        $time = base_convert((string) (int) (microtime(true) * 1000), 10, 36);
        self::$counter = (self::$counter + 1) % 46656; // 36^3
        $count = str_pad(base_convert((string) self::$counter, 10, 36), 3, '0', STR_PAD_LEFT);
        $rand = base_convert((string) random_int(0, 2176782335), 10, 36); // 36^6-ish
        $rand = str_pad($rand, 6, '0', STR_PAD_LEFT);
        $fingerprint = substr(base_convert((string) (getmypid() ?: 1), 10, 36) . bin2hex(random_bytes(2)), 0, 4);
        $id = 'c' . $time . $count . $fingerprint . $rand;
        return substr($id, 0, 30);
    }
}
