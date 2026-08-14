<?php
/**
 * KINAS GROUP — Public Identity (username) helpers.
 * Single source of truth for username normalization, validation,
 * uniqueness checks and public display ("@username").
 */

if (!function_exists('kinas_normalize_username')) {
function kinas_normalize_username(?string $raw): string
{
    return ltrim(strtolower(trim((string)$raw)), '@');
}
}

if (!function_exists('kinas_reserved_usernames')) {
function kinas_reserved_usernames(): array
{
    return [
        'admin','administrator','root','system','support','help','kinas','kinasgroup',
        'agent','agents','user','users','buyer','buyers','staff','moderator','mod',
        'official','verified','security','noreply','no-reply','mail','webmaster',
        'postmaster','hostmaster','abuse','info','contact','sales','billing',
        'marketplace','automobile','volt','williams','williamsconnecthome',
        'dashboard','login','logout','register','signup','search','api','www','ftp',
    ];
}
}

if (!function_exists('kinas_username_error')) {
/** @return string|null error message, or null when the username is valid */
function kinas_username_error(string $username): ?string
{
    $len = strlen($username);
    if ($len < 3)  return 'Username must be at least 3 characters.';
    if ($len > 20) return 'Username must not exceed 20 characters.';
    if (!preg_match('/^[a-z][a-z0-9_.]*$/', $username)) {
        return 'Username must start with a letter and use only letters, numbers, underscores and dots.';
    }
    if (preg_match('/[_.]{2,}/', $username) || substr($username, -1) === '_' || substr($username, -1) === '.') {
        return 'Username cannot contain consecutive or trailing underscores/dots.';
    }
    if (in_array($username, kinas_reserved_usernames(), true)) {
        return 'This username is reserved.';
    }
    return null;
}
}

if (!function_exists('kinas_username_taken')) {
function kinas_username_taken(PDO $db, string $username, ?int $exceptUserId = null): bool
{
    try {
        $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        $params = [$username];
        if ($exceptUserId !== null) { $sql .= " AND id != ?"; $params[] = $exceptUserId; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        // Column missing (migration not run) — fail closed.
        error_log('kinas_username_taken: ' . $e->getMessage());
        return true;
    }
}
}

if (!function_exists('kinas_public_display_name')) {
/** Public identity: "@username" when set, otherwise a safe fallback. */
function kinas_public_display_name(?string $username, ?string $fallback = null): string
{
    $username = trim((string)$username);
    if ($username !== '') return '@' . $username;
    $fallback = trim((string)$fallback);
    return $fallback !== '' ? $fallback : 'KINAS Member';
}
}
