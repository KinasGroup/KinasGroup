<?php
/**
 * ADMIN SIDEBAR — KINAS GROUP
 *
 * This file normally only calls je_render_sidebar().
 *
 * This amended version keeps the existing admin sidebar exactly as it is,
 * then safely adds the new Product Reviews moderation link.
 *
 * Direct admin URL:
 * /admin/reviews.php
 */

require_once __DIR__ . '/../../includes/je-sidebar.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$sidebarHtml = '';

if (function_exists('je_render_sidebar')) {
    ob_start();

    $returnedSidebar = je_render_sidebar('admin', $currentPage, 2);

    $sidebarHtml = (string)ob_get_clean();

    // Some sidebar renderers return HTML instead of echoing it.
    if ($sidebarHtml === '' && is_string($returnedSidebar)) {
        $sidebarHtml = $returnedSidebar;
    }
}

echo $sidebarHtml;
?>
<script>
    (function () {
        function kinasAddAdminReviewsLink() {
            // Prevent duplicate links.
            if (document.getElementById('adminReviewsSidebarLink')) {
                return;
            }

            if (document.querySelector('a[href="/admin/reviews.php"]')) {
                return;
            }

            var sidebar = document.querySelector('.je-sidebar')
                || document.querySelector('aside')
                || document.querySelector('nav')
                || document.querySelector('.sidebar');

            if (!sidebar) {
                return;
            }

            var link = document.createElement('a');

            link.id = 'adminReviewsSidebarLink';
            link.href = '/admin/reviews.php';
            link.innerHTML = '<i class="fas fa-star"></i> Product Reviews';

            link.style.display = 'flex';
            link.style.alignItems = 'center';
            link.style.gap = '10px';
            link.style.padding = '12px 18px';
            link.style.marginTop = '6px';
            link.style.borderRadius = '10px';
            link.style.textDecoration = 'none';
            link.style.fontWeight = '600';
            link.style.fontSize = '14px';
            link.style.color = 'inherit';
            link.style.background = 'transparent';

            if (window.location.pathname.indexOf('/admin/reviews.php') !== -1) {
                link.style.background = '#C6A43F';
                link.style.color = '#0A0A0A';
            }

            // If the sidebar uses a list-based menu, insert as a list item.
            var lists = sidebar.querySelectorAll('ul');

            if (lists.length > 0) {
                var targetList = lists[lists.length - 1];
                var listItem = document.createElement('li');

                listItem.appendChild(link);
                targetList.appendChild(listItem);
            } else {
                sidebar.appendChild(link);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', kinasAddAdminReviewsLink);
        } else {
            kinasAddAdminReviewsLink();
        }
    })();
</script>
