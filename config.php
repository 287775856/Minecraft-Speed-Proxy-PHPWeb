<?php
// 配置文件
define('API_BASE_URL', 'http://127.0.0.1:20220/api/');
define('ADMIN_PASSWORD', '你的API密码'); // 与 Minecraft-Speed-Proxy 配置一致
define('TOKEN_EXPIRY', 3600); // token 有效期（秒）
define('DB_PATH', __DIR__ . '/data/whitelist_codes.sqlite');
define('DB_DSN', 'sqlite:' . DB_PATH);
?>
