<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'api-helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$password = $_POST['password'];
    if (login($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = "登录失败，请检查密码";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 代理服务器 - 登录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Minecraft 代理服务器管理</h1>
        <form method="POST" action="login.php">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="form-group">
                <label for="password">密码:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">登录</button>
        </form>
    </div>
</body>
</html>
