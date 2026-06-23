function je_render_sidebar(string $role, string $currentPage, int $headerDepth = 1): void
{
    $base = str_repeat('../', $headerDepth);

    // ── FORCE REFRESH SUPER AGENT STATUS ──────────────────────────────
    // This ensures the sidebar shows correctly even if the session is stale
    if ($role === 'agent' && isset($_SESSION['user_id'])) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT is_super_agent FROM agent_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $_SESSION['is_super_agent'] = !empty($row['is_super_agent']);
            }
        } catch (Exception $e) {
            // ignore - keep existing session value
        }
    }
    // ────────────────────────────────────────────────────────────────────
