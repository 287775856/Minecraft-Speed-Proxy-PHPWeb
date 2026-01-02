<?php
require_once 'api-helper.php';
require_once 'storage.php';

$successMessage = null;
$errorMessage = null;
$infoMessage = null;
$warningMessage = null;
$record = null;

$adminToken = getAdminToken();
$removedCodes = $adminToken ? cleanupExpiredActivations($adminToken) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'lookup';
    $codeInput = $_POST['code'] ?? '';
    $code = normalizeActivationCode($codeInput);

    if ($code === '') {
        $errorMessage = '请输入激活码。';
    } else {
        $codes = loadActivationCodes();
        list($index, $record) = findActivationCode($codes, $code);

        if ($record === null) {
            if (in_array($code, $removedCodes, true)) {
                $errorMessage = '该激活码已到期并自动删除。';
            } else {
                $errorMessage = '激活码无效。';
            }
        } elseif (empty($record['used_at'])) {
            $errorMessage = '该激活码尚未激活。';
        } else {
            $expiresAt = $record['expires_at'] ?? null;
            if ($expiresAt && $expiresAt < time()) {
                $errorMessage = '该账号已到期，自动删除处理中。';
            } elseif ($action === 'update') {
                $newUsername = trim($_POST['new_username'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $newUsername)) {
                    $errorMessage = '用户名非法，请输入 3-16 位字母、数字或下划线。';
                } elseif (!$adminToken) {
                    $errorMessage = '无法连接到服务器，请稍后再试。';
                } else {
                    $currentUsername = $record['used_by'] ?? '';
                    if ($currentUsername === $newUsername) {
                        $infoMessage = '新旧游戏用户名一致，无需修改。';
                    } else {
                        $addResponse = makeApiRequest('add_whitelist_user', 'POST', ['username' => $newUsername], $adminToken);
                        if ($addResponse['code'] == 200 && ($addResponse['data']['status'] ?? null) == 200) {
                            if ($currentUsername !== '') {
                                $removeResponse = makeApiRequest('remove_whitelist_user', 'POST', ['username' => $currentUsername], $adminToken);
                                if (!($removeResponse['code'] == 200 && ($removeResponse['data']['status'] ?? null) == 200)) {
                                    $warningMessage = '新用户名已添加，但旧用户名未能自动移除，请联系管理员处理。';
                                }
                            }

                            updateActivationCodeUser($code, $newUsername);
                            $codes = loadActivationCodes();
                            list(, $record) = findActivationCode($codes, $code);
                            $successMessage = '已成功更新游戏用户名。';
                        } else {
                            $apiMessage = $addResponse['data']['message'] ?? 'API 返回错误';
                            $errorMessage = '修改失败: ' . $apiMessage;
                        }
                    }
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
    <title>Minecraft 代理服务器 - 激活信息查询</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>激活信息查询</h1>
            <a href="redeem.php" class="btn btn-sm">返回激活页面</a>
        </header>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($warningMessage): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($warningMessage); ?></div>
        <?php endif; ?>
        <?php if ($infoMessage): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($infoMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>查询激活状态</h2>
            <form method="POST" action="activation-status.php">
                <input type="hidden" name="action" value="lookup">
                <div class="form-row">
                    <div class="form-group">
                        <label for="code">激活码</label>
                        <input type="text" id="code" name="code" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">查询</button>
            </form>
        </div>

        <?php if ($record && !empty($record['used_at'])): ?>
            <div class="section">
                <h2>激活信息</h2>
                <table>
                    <tbody>
                        <tr>
                            <th>激活码</th>
                            <td><?php echo htmlspecialchars($record['code']); ?></td>
                        </tr>
                        <tr>
                            <th>游戏用户名</th>
                            <td><?php echo htmlspecialchars($record['used_by'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>激活时间</th>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $record['used_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>到期时间</th>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $record['expires_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>状态</th>
                            <td>
                                <?php if (($record['expires_at'] ?? 0) < time()): ?>
                                    已到期
                                <?php else: ?>
                                    使用中
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (($record['expires_at'] ?? 0) >= time()): ?>
                <div class="section">
                    <h2>修改游戏用户名</h2>
                    <form method="POST" action="activation-status.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($record['code']); ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_username">新游戏用户名</label>
                                <input type="text" id="new_username" name="new_username" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">更新</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
