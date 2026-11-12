<?php

declare(strict_types=1);

namespace App\Libraries;

/** Single-use, session-bound form challenges; no external CAPTCHA dependency. */
class ReportFormGuard
{
    public static function issue(): string
    {
        $tokens = (array) session()->get('citizen_form_tokens');
        $tokens = array_filter($tokens, static fn ($time) => $time >= time() - 3600);
        $token = bin2hex(random_bytes(24));
        $tokens[$token] = time();
        session()->set('citizen_form_tokens', array_slice($tokens, -5, null, true));
        return $token;
    }

    public static function verify(array $post, ?int $now = null): bool
    {
        $now ??= time();
        $token = $post['report_token'] ?? null;
        $trap = $post['website'] ?? '';
        if (!is_string($token) || !is_string($trap) || $trap !== '') return false;
        $tokens = (array) session()->get('citizen_form_tokens');
        $started = $tokens[$token] ?? null;
        if (!is_int($started)) return false;
        unset($tokens[$token]);
        session()->set('citizen_form_tokens', $tokens);
        return $now - $started >= config(\Config\CitizenReports::class)->minFormSeconds && $now - $started <= 3600;
    }
}
