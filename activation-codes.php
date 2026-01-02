<?php
session_start();
require_once 'api-helper.php';
require_once 'storage.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$successMessage = null;
$errorMessage = null;
$generatedCodes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expiresInDays = $_POST['expires_in_days'] ?? '';
    $note = $_POST['note'] ?? '';
    $quantity = $_POST['quantity'] ?? '';

    if (!is_numeric($expiresInDays) || (int) $expiresInDays < 1) {
        $errorMessage = '有效期必须是大于等于 1 的数字（天）。';
    } elseif (!is_numeric($quantity) || (int) $quantity < 1) {
        $errorMessage = '生成数量必须是大于等于 1 的数字。';
    } else {
        $generatedCodes = createActivationCodes((int) $quantity, (int) $expiresInDays, $note);
        $successMessage = '已成功生成激活码。';
    }
}

$allCodes = loadActivationCodes();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 代理服务器 - 激活码管理</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>激活码管理</h1>
            <div>
                <a href="index.php" class="btn btn-sm">返回管理面板</a>
                <a href="logout.php" class="logout-btn btn btn-sm">退出</a>
            </div>
        </header>

        <?php if ($successMessage): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>生成激活码</h2>
            <form method="POST" action="activation-codes.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="expires_in_days">有效期（天）</label>
                        <input type="number" id="expires_in_days" name="expires_in_days" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="quantity">生成数量</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="100" required>
                    </div>
                    <div class="form-group">
                        <label for="note">备注</label>
                        <input type="text" id="note" name="note" placeholder="可选">
                    </div>
                </div>
                <button type="submit" class="btn btn-success">生成</button>
            </form>
        </div>

        <?php if (!empty($generatedCodes)): ?>
            <div class="section">
                <h2>本次生成结果</h2>
                <table>
                    <thead>
                        <tr>
                            <th>激活码</th>
                            <th>有效期至</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generatedCodes as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['code']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $record['expires_at'])); ?></td>
                                <td><?php echo htmlspecialchars($record['note'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>全部激活码</h2>
            <table>
                <thead>
                    <tr>
                        <th>激活码</th>
                        <th>有效期至</th>
                        <th>备注</th>
                        <th>状态</th>
                        <th>使用者</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($allCodes) as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['code']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', $record['expires_at'])); ?></td>
                            <td><?php echo htmlspecialchars($record['note'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($record['used_at'])): ?>
                                    已使用
                                <?php elseif ($record['expires_at'] < time()): ?>
                                    已过期
                                <?php else: ?>
                                    未使用
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($record['used_by'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
