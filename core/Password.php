<?php

class Password
{
    public static function hash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify($password, $hash)
    {
        if (!$hash) {
            return false;
        }

        if (password_verify($password, $hash)) {
            return true;
        }

        return hash_equals((string)$hash, (string)$password);
    }

    public static function needsRehash($hash)
    {
        return !password_get_info($hash)['algo'] || password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
