<?php
// test-r2.php - Test R2 upload

require_once __DIR__ . '/includes/file-upload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $uploader = new FileUpload('general');
    $result = $uploader->upload($_FILES['test_file']);
    
    echo '<pre>';
    print_r($result);
    echo '</pre>';
    
    if ($result['success']) {
        echo '<img src="' . $result['filepath'] . '" style="max-width: 400px;">';
        echo '<br><strong>✅ Upload Successful!</strong>';
    } else {
        echo '<strong>❌ Error:</strong> ' . $result['error'];
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Test Upload</title></head>
<body>
    <h1>Test Upload</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" accept="image/*">
        <button type="submit">Upload</button>
    </form>
</body>
</html>
