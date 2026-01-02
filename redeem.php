<?php
require_once 'api-helper.php';
require_once 'storage.php';

$successMessage = null;
$errorMessage = null;

$adminToken = getAdminToken();
if ($adminToken) {
    cleanupExpiredActivations($adminToken);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeInput = $_POST['code'] ?? '';
    $username = $_POST['username'] ?? '';

    $code = normalizeActivationCode($codeInput);
    $username = trim($username);

    if ($code === '') {
        $errorMessage = '请输入激活码。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username)) {
        $errorMessage = '用户名非法，请输入 3-16 位字母、数字或下划线。';
    } else {
        $codes = loadActivationCodes();
        [$index, $record] = findActivationCode($codes, $code);

        if ($record === null) {
            $errorMessage = '激活码无效。';
        } elseif (!empty($record['used_at'])) {
            $errorMessage = '该激活码已使用。';
        } elseif (!empty($record['expires_at']) && $record['expires_at'] < time()) {
            $errorMessage = '该激活码已过期。';
        } else {
            if (!$adminToken) {
                $errorMessage = '无法连接到服务器，请稍后再试。';
            } else {
                $response = makeApiRequest('add_whitelist_user', 'POST', ['username' => $username], $adminToken);
                if ($response['code'] == 200 && ($response['data']['status'] ?? null) == 200) {
                    markActivationCodeUsed($code, $username);
                    $successMessage = '兑换成功，已将用户加入白名单。';
                } else {
                    $apiMessage = $response['data']['message'] ?? 'API 返回错误';
                    $errorMessage = '兑换失败: ' . $apiMessage;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 代理服务器 - 激活码兑换</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>激活码兑换</h1>
            <div>
                <a href="activation-status.php" class="btn btn-sm">查询激活状态</a>
                <a href="login.php" class="btn btn-sm">管理员登录</a>
            </div>
        </header>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="section">
            <form method="POST" action="redeem.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="code">激活码</label>
                        <input type="text" id="code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="username">游戏用户名</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">兑换</button>
            </form>
        </div>
    </div>
</body>
</html>
