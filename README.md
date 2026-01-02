# Minecraft-Speed-Proxy-PHPWeb
![示例图](https://www.linbei.de/example1.jpeg)
Minecraft Speed Proxy的php网页管理

支持添加删除白名单、查询在线玩家、查询log等功能

上传到支持php的web空间下

修改config.php
```php
define('API_BASE_URL', 'http://127.0.0.1:20220/api/');
define('ADMIN_PASSWORD', '你的API密码'); // 与 Minecraft-Speed-Proxy 配置一致
```
修改api地址和api密码后即可使用

## 定时清理过期激活码

通过 cron 自动执行清理脚本（仅支持 CLI 执行）：

```bash
*/30 * * * * /usr/bin/php /path/to/Minecraft-Speed-Proxy-PHPWeb/cron-cleanup.php >> /path/to/cleanup.log 2>&1
```
