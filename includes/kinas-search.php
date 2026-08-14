<?php
/**
* KINAS GROUP — Intelligent search helpers.
* Tokenizes a query and matches each word (with plural tolerance) against a
* concatenated "haystack" of listing fields, then ranks by relevance.
* All functions are guarded so this file can be included anywhere safely.
*/
if (!function_exists('kinas_search_tokens')) {
function kinas_search_tokens(string $q): array {
$q = function_exists('mb_strtolower') ? mb_strtolower(trim($q)) : strtolower(trim($q));
if ($q === '') return [];
$parts = preg_split('/[^a-z0-9]+/', $q, -1, PREG_SPLIT_NO_EMPTY);
$stop = ['the','a','an','and','or','for','with','in','on','of','to','at','by'];
$tokens = [];
foreach ($parts as $p) {
if (strlen($p) < 2) continue;
if (in_array($p, $stop, true)) continue;
$tokens[] = $p;
}
return array_values(array_unique($tokens));
}
}
if (!function_exists('kinas_search_where')) {
/** AND across tokens against a haystack SQL expression. Returns [sql, params] or null. */
function kinas_search_where(string $haystack, array $tokens): ?array {
if (empty($tokens)) return null;
$conds = []; $params = [];
foreach ($tokens as $t) {
$conds[] = "$haystack LIKE ?";
$params[] = "%$t%";
if (strlen($t) > 3 && substr($t, -1) === 's') {
$conds[] = "$haystack LIKE ?";
$params[] = '%' . substr($t, 0, -1) . '%';
}
}
// Each token group must match (AND), but within a token, plural/singular OR.
// Group them properly:
$grouped = []; $gp = [];
foreach ($tokens as $t) {
$g = ["$haystack LIKE ?"]; $p = ["%$t%"];
if (strlen($t) > 3 && substr($t, -1) === 's') { $g[] = "$haystack LIKE ?"; $p[] = '%' . substr($t,0,-1) . '%'; }
$grouped[] = '(' . implode(' OR ', $g) . ')';
$gp = array_merge($gp, $p);
}
return [implode(' AND ', $grouped), $gp];
}
}
if (!function_exists('kinas_search_score')) {
/** PHP-side relevance: title hits weigh 2, haystack hits weigh 1. */
function kinas_search_score(string $title, string $hay, array $tokens): int {
$title = function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title);
$hay = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
$score = 0;
foreach ($tokens as $t) {
$variants = [$t];
if (strlen($t) > 3 && substr($t, -1) === 's') $variants[] = substr($t, 0, -1);
$hitTitle = false; $hitHay = false;
foreach ($variants as $v) {
if (strpos($title, $v) !== false) $hitTitle = true;
if (strpos($hay, $v) !== false) $hitHay = true;
}
if ($hitTitle) $score += 2;
elseif ($hitHay) $score += 1;
}
return $score;
}
}
