<?php

namespace App\Support;

class PlanningMailText
{
    /** Escape user-provided titles before inserting them into Markdown mail. */
    public static function escape(string $text): string
    {
        $text = e(preg_replace('/\s+/u', ' ', $text) ?? '');
        $special = ['\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '#', '+', '-', '.', '!', '|', '>', '~'];
        $escaped = array_map(fn (string $character) => '\\'.$character, $special);

        return str_replace($special, $escaped, $text);
    }
}
