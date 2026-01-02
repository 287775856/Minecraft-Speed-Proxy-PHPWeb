<?php
require_once 'api-helper.php';
require_once 'storage.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo '仅支持 CLI 执行。';
    exit;
}

$token = getAdminToken();
$removedCodes = cleanupExpiredActivations($token);
$timestamp = date('Y-m-d H:i:s');
$removedCount = count($removedCodes);

echo sprintf("[%s] 清理完成，已删除 %d 个过期激活码。\n", $timestamp, $removedCount);

if ($removedCount > 0) {
    echo "已删除激活码: " . implode(', ', $removedCodes) . "\n";
}

if ($token === null) {
    echo "警告：无法获取管理 Token，仅清理未绑定用户的过期激活码。\n";
}
