<?php

if (!function_exists('get_first_digits_or_origin_string')) {
    function get_first_digits_or_origin_string(string $string)
    {
        preg_match('/\d+/', $string, $matches);
        return $matches[0] ?? $string;
    }
}
