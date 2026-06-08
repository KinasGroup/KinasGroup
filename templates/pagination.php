<?php
function renderPagination($pagination, $baseUrl = '?') {
    if ($pagination['total'] <= 1) return '';
    
    // Remove existing page parameter from URL
    $baseUrl = preg_replace('/[?&]page=\d+/', '', $baseUrl);
    $connector = strpos($baseUrl, '?') !== false ? '&' : '?';
    
    ob_start();
?>
    <nav class="pagination-container" aria-label="Page navigation">
        <div class="pagination">
            <?php if ($pagination['hasPrev']): ?>
                <a href="<?php echo $baseUrl . $connector; ?>page=<?php echo $pagination['current'] - 1; ?>" 
                   aria-label="Previous page">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php else: ?>
                <span class="disabled">
                    <i class="fas fa-chevron-left"></i> Previous
                </span>
            <?php endif; ?>
            
            <?php
            // First page
            if ($pagination['current'] > 3): ?>
                <a href="<?php echo $baseUrl . $connector; ?>page=1">1</a>
                <?php if ($pagination['current'] > 4): ?>
                    <span class="dots">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php foreach ($pagination['pages'] as $page): ?>
                <a href="<?php echo $baseUrl . $connector; ?>page=<?php echo $page; ?>" 
                   class="<?php echo $page === $pagination['current'] ? 'active' : ''; ?>">
                    <?php echo $page; ?>
                </a>
            <?php endforeach; ?>
            
            <?php if ($pagination['current'] < $pagination['total'] - 2): ?>
                <?php if ($pagination['current'] < $pagination['total'] - 3): ?>
                    <span class="dots">...</span>
                <?php endif; ?>
                <a href="<?php echo $baseUrl . $connector; ?>page=<?php echo $pagination['total']; ?>">
                    <?php echo $pagination['total']; ?>
                </a>
            <?php endif; ?>
            
            <?php if ($pagination['hasNext']): ?>
                <a href="<?php echo $baseUrl . $connector; ?>page=<?php echo $pagination['current'] + 1; ?>" 
                   aria-label="Next page">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled">
                    Next <i class="fas fa-chevron-right"></i>
                </span>
            <?php endif; ?>
        </div>
        
        <p class="pagination-info">
            Page <?php echo $pagination['current']; ?> of <?php echo $pagination['total']; ?>
        </p>
    </nav>
    
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 40px 0 20px;
        }
        
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: white;
            border: 1px solid #E0E0E0;
            color: var(--je-black, #0A0A0A);
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: var(--je-gold, #C6A43F);
            border-color: var(--je-gold, #C6A43F);
            color: var(--je-black, #0A0A0A);
        }
        
        .pagination .active {
            background: var(--je-gold, #C6A43F);
            border-color: var(--je-gold, #C6A43F);
            color: var(--je-black, #0A0A0A);
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .dots {
            border: none;
            background: transparent;
        }
        
        .pagination-info {
            text-align: center;
            color: var(--je-gray-dark, #666666);
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .pagination a, .pagination span {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
<?php
    return ob_get_clean();
}

function renderSimplePagination($currentPage, $totalPages, $urlPattern = '/page/%d') {
    if ($totalPages <= 1) return '';
    
    ob_start();
?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?php echo sprintf($urlPattern, $i); ?>" 
               class="<?php echo $i === $currentPage ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
<?php
    return ob_get_clean();
}
?>
