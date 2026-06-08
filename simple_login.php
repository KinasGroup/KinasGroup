<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($email === 'test@kinasgroup.com' && $password === 'test123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Test User';
        echo "<h2 style='color:green'>Login successful!</h2>";
        exit;
    } else {
        $error = "Invalid credentials. Use test@kinasgroup.com / test123";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Simple Login Test</title></head>
<body style="font-family: Arial; padding: 50px; background: #f0f0f0;">
    <div style="max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;">
        <h1 style="color: #d4af37;">KINAS GROUP Login Test</h1>
        <?php if ($error): ?>
            <div style="background: #ffebee; color: red; padding: 10px; margin-bottom: 20px;"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div style="margin-bottom: 15px;">
                <input type="email" name="email" placeholder="Email" style="width: 100%; padding: 10px;" value="test@kinasgroup.com">
            </div>
            <div style="margin-bottom: 15px;">
                <input type="password" name="password" placeholder="Password" style="width: 100%; padding: 10px;" value="test123">
            </div>
            <button type="submit" style="background: #d4af37; color: #1a1a2e; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Login</button>
        </form>
    </div>
</body>
</html>
