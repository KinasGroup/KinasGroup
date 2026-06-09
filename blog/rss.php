<?php
/**
 * KINAS GROUP — Blog: RSS Feed
 * Pulls the 20 most recent published posts.
 */
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance()->getConnection();
$siteUrl = defined('SITE_URL') ? SITE_URL : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'kinas-group.com'));
$blogUrl = $siteUrl . '/blog';

header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>KINAS GROUP Blog</title>
        <link><?= htmlspecialchars($blogUrl) ?></link>
        <description>Latest news, insights, and updates from KINAS GROUP divisions</description>
        <language>en-us</language>
        <lastBuildDate><?= date('r') ?></lastBuildDate>
        <atom:link href="<?= htmlspecialchars($blogUrl) ?>/rss.php" rel="self" type="application/rss+xml"/>

        <?php
            $stmt = $db->query("
                SELECT p.*, u.name AS author_name
                FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
                WHERE p.published = 1
                ORDER BY p.published_at DESC
                LIMIT 20
            ");
            $categoryLabels = [
                'automobile'  => 'Automobile',
                'realestate'  => 'Real Estate',
                'solar'       => 'Solar Energy',
                'marketplace' => 'Marketplace',
                'news'        => 'Company News',
                'guides'      => "Buyer's Guides",
            ];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p):
                $link = $blogUrl . '/post.php?id=' . (int)$p['id'];
                $pub  = !empty($p['published_at']) ? strtotime($p['published_at']) : strtotime($p['created_at']);
                $cat  = $categoryLabels[$p['category']] ?? ucfirst($p['category']);
        ?>
        <item>
            <title><?= htmlspecialchars($p['title']) ?></title>
            <link><?= htmlspecialchars($link) ?></link>
            <guid isPermaLink="true"><?= htmlspecialchars($link) ?></guid>
            <pubDate><?= date('r', $pub) ?></pubDate>
            <category><?= htmlspecialchars($cat) ?></category>
            <description><![CDATA[<?= htmlspecialchars(mb_strimwidth((string)($p['excerpt'] ?: $p['body']), 0, 500, '…')) ?>]]></description>
            <?php if (!empty($p['author_name'])): ?>
            <author><?= htmlspecialchars($p['author_name']) ?></author>
            <?php endif; ?>
        </item>
        <?php endforeach; ?>
    </channel>
</rss>
