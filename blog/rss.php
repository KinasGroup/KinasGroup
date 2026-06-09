<?php
header('Content-Type: application/rss+xml; charset=UTF-8');

$siteUrl = 'https://' . $_SERVER['HTTP_HOST'];
$blogUrl = $siteUrl . '/blog';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>KINAS GROUP Blog</title>
        <link><?php echo $blogUrl; ?></link>
        <description>Latest news, insights, and updates from KINAS GROUP divisions</description>
        <language>en-us</language>
        <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
        <atom:link href="<?php echo $blogUrl; ?>/rss.php" rel="self" type="application/rss+xml"/>
        
        <item>
            <title>The Future of Luxury: Trends Shaping 2024</title>
            <link><?php echo $blogUrl; ?>/post.php?id=1</link>
            <guid isPermaLink="true"><?php echo $blogUrl; ?>/post.php?id=1</guid>
            <pubDate><?php echo date('r', strtotime('2024-05-15')); ?></pubDate>
            <category>Automobile</category>
            <description><![CDATA[<p>Explore the emerging trends in luxury automobiles, real estate, and sustainable energy that are redefining the industry landscape in 2024 and beyond.</p>]]></description>
            <author>John Smith</author>
        </item>
        
        <item>
            <title>Top 10 Luxury Cars of 2024</title>
            <link><?php echo $blogUrl; ?>/post.php?id=2</link>
            <guid isPermaLink="true"><?php echo $blogUrl; ?>/post.php?id=2</guid>
            <pubDate><?php echo date('r', strtotime('2024-05-10')); ?></pubDate>
            <category>Automobile</category>
            <description><![CDATA[<p>Discover the most anticipated luxury vehicles hitting the market this year, from electric supercars to hybrid SUVs.</p>]]></description>
            <author>John Smith</author>
        </item>
        
        <item>
            <title>Investing in Premium Properties: A Guide</title>
            <link><?php echo $blogUrl; ?>/post.php?id=3</link>
            <guid isPermaLink="true"><?php echo $blogUrl; ?>/post.php?id=3</guid>
            <pubDate><?php echo date('r', strtotime('2024-05-05')); ?></pubDate>
            <category>Real Estate</category>
            <description><![CDATA[<p>Key factors to consider when investing in high-end real estate, including location, amenities, and market trends.</p>]]></description>
            <author>Sarah Johnson</author>
        </item>
        
        <item>
            <title>How Solar Energy is Powering Luxury Homes</title>
            <link><?php echo $blogUrl; ?>/post.php?id=4</link>
            <guid isPermaLink="true"><?php echo $blogUrl; ?>/post.php?id=4</guid>
            <pubDate><?php echo date('r', strtotime('2024-04-28')); ?></pubDate>
            <category>Solar Energy</category>
            <description><![CDATA[<p>The integration of sustainable energy in modern luxury architecture is transforming how we think about premium living.</p>]]></description>
            <author>Mike Chen</author>
        </item>
        
        <item>
            <title>Authenticating Luxury Goods: What to Know</title>
            <link><?php echo $blogUrl; ?>/post.php?id=5</link>
            <guid isPermaLink="true"><?php echo $blogUrl; ?>/post.php?id=5</guid>
            <pubDate><?php echo date('r', strtotime('2024-04-20')); ?></pubDate>
            <category>Marketplace</category>
            <description><![CDATA[<p>Tips for verifying the authenticity of high-end products when shopping on the luxury marketplace.</p>]]></description>
            <author>David Wilson</author>
        </item>
    </channel>
</rss>
