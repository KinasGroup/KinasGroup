<?php
// test-r2.php - Test R2 upload directly

require_once __DIR__ . '/includes/r2-upload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    try {
        $r2 = new R2Upload('general');
        $result = $r2->upload($_FILES['test_file']);
        
        echo '<pre>';
        print_r($result);
        echo '</pre>';
        
        if ($result['success']) {
            echo '<img src="' . $result['filepath'] . '" style="max-width: 400px;">';
            echo '<br><strong>✅ R2 Upload Successful!</strong>';
        } else {
            echo '<strong>❌ Error:</strong> ' . $result['error'];
        }
    } catch (Exception $e) {
        echo '<strong>❌ Exception:</strong> ' . $e->getMessage();
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Test R2 Upload</title></head>
<body>
    <h1>Test R2 Upload</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" accept="image/*">
        <button type="submit">Upload to R2</button>
    </form>
</body>
</html>
