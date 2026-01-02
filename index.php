<?php
session_start();
require_once 'api-helper.php';
require_once 'storage.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$token = getToken();
cleanupExpiredActivations();

cleanupExpiredActivations($token);

// 获取白名单状态
$whitelist = makeApiRequest('get_whitelist', 'GET', null, $token);
$blacklist = makeApiRequest('get_blacklist', 'GET', null, $token);
$onlineUsers = makeApiRequest('get_online_users', 'GET', null, $token);

// 处理添加/删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = $_POST['username'] ?? '';
    $listType = $_POST['list_type'] ?? '';
    
    if ($username && $listType) {
        switch ($action) {
            case 'add':
                $endpoint = $listType === 'whitelist' ? 'add_whitelist_user' : 'add_blacklist_user';
                break;
            case 'remove':
                $endpoint = $listType === 'whitelist' ? 'remove_whitelist_user' : 'remove_blacklist_user';
                break;
            case 'toggle':
                $endpoint = $_POST['whitelist_status'] === 'true' ? 'enable_whitelist' : 'disable_whitelist';
                break;
            default:
                $endpoint = null;
        }
        
        if ($endpoint) {
            $data = $action === 'toggle' ? null : ['username' => $username];
            $response = makeApiRequest($endpoint, 'POST', $data, $token);
            
            // 刷新数据
            if ($response['code'] == 200) {
                header("Location: index.php");
                exit;
            }
        }
    }
}


// 处理白名单状态切换
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_whitelist') {
        $action = $_POST['whitelist_status'] ?? '';
        
        try {
            if ($action === 'enable') {
                $response = makeApiRequest('enable_whitelist', 'GET', null, $token);
                $redirectParam = 'whitelist_enabled=1';
            } elseif ($action === 'disable') {
                $response = makeApiRequest('disable_whitelist', 'GET', null, $token);
                $redirectParam = 'whitelist_enabled=0';
            }
            
            if ($response['code'] == 200 && $response['data']['status'] == 200) {
                header("Location: index.php?$redirectParam");
            } else {
                header("Location: index.php?error=" . urlencode($response['data']['message'] ?? '操作失败'));
            }
            exit;
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode($e->getMessage()));
            exit;
        }
    }
}
// 处理踢出请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'kick') {
        $username = $_POST['username'] ?? '';
        
        if (empty($username)) {
            $error = "用户名不能为空";
        } else {
            try {
                $response = makeApiRequest('kick_player', 'POST', ['username' => $username], $token);
                
                if ($response['code'] == 200) {
                    if ($response['data']['status'] == 200) {
                        header("Location: index.php?kick_success=1");
                        exit;
                    } else {
                        $error = "踢出失败: " . ($response['data']['message'] ?? 'API返回错误');
                    }
                } else {
                    $error = "API请求失败，HTTP状态码: " . $response['code'];
                }
            } catch (Exception $e) {
                $error = "发生异常: " . $e->getMessage();
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
    <title>Minecraft 代理服务器 - 管理面板</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Minecraft 代理服务器管理</h1>
            <div>
                <a href="activation-codes.php" class="btn btn-sm">激活码管理</a>
                <a href="logout.php" class="logout-btn btn btn-sm">退出</a>
            </div>
        </header>
                <?php if (isset($_GET['whitelist_enabled'])): ?>
            <div class="alert alert-info">
                白名单已<?php echo $_GET['whitelist_enabled'] === '1' ? '启用' : '禁用'; ?>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                操作失败: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard">
                    <!-- 服务器状态 -->
<div class="section server-status">
    <?php
    $serverTime = makeApiRequest('get_start_time', 'GET', null, $token);
    if (
        $serverTime['code'] == 200
        && isset($serverTime['data']['now_time'], $serverTime['data']['start_time'])
    ) {
        $uptime = $serverTime['data']['now_time'] - $serverTime['data']['start_time'];
        $uptimeStr = gmdate("H:i:s", $uptime);
        echo "<div>服务器已运行: <strong>{$uptimeStr}</strong></div>";
        echo "<div>启动时间: <strong>" . date('Y-m-d H:i:s', $serverTime['data']['start_time']) . "</strong></div>";
    } else {
        echo "<div>服务器已运行: <strong>--:--:--</strong></div>";
        echo "<div>启动时间: <strong>未知</strong></div>";
    }
    ?>
    <a href="logs.php" class="btn">查看完整日志</a>
</div>
            <!-- 在线用户 -->
            <div class="section">
                <h2>在线用户 (<?php echo count($onlineUsers['data']['online_users'] ?? []); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>IP</th>
                            <th>代理目标</th>
                            <th>在线时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($onlineUsers['data']['online_users'] ?? [] as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['ip']); ?></td>
                                <td><?php echo htmlspecialchars($user['proxy_target']); ?></td>
                                <td><?php echo gmdate("H:i:s", time() - $user['online_time_stamp']); ?></td>
                                <td>
                                    <form method="POST" action="index.php" style="display:inline;">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                        <input type="hidden" name="list_type" value="blacklist">
                                        <input type="hidden" name="action" value="add">
                                        <button type="submit" class="btn btn-sm btn-danger">加入黑名单</button>
                                    </form>
                                    <!-- 在页面顶部显示踢出结果 -->
<?php if (isset($_GET['kick_success'])): ?>
    <div class="alert alert-success">玩家已成功踢出</div>
<?php elseif (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- 改进踢出按钮，添加确认对话框 -->
<form method="POST" action="index.php" style="display:inline;" 
      onsubmit="return confirm('确定要踢出玩家 <?php echo htmlspecialchars($user['username']); ?> 吗？')">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
    <input type="hidden" name="action" value="kick">
    <button type="submit" class="btn btn-sm btn-warning">踢出</button>
</form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 白名单管理 -->
            <div class="section">
                <h2>白名单管理</h2>
                <!-- 白名单状态切换 -->
<div class="toggle-container">
    <span>白名单状态: </span>
    <?php $whitelistEnabled = $whitelist['data']['whitelist_status'] ?? false; ?>
    <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="toggle_whitelist">
        <input type="hidden" name="whitelist_status" value="<?php echo $whitelistEnabled ? 'disable' : 'enable'; ?>">
        <button type="submit" class="btn btn-sm <?php echo $whitelistEnabled ? 'btn-success' : 'btn-danger'; ?>">
            <?php echo $whitelistEnabled ? '已启用' : '已禁用'; ?>
        </button>
    </form>
</div>
                
                <form method="POST" action="index.php" class="add-form">
                    <input type="text" name="username" placeholder="输入用户名" required>
                    <input type="hidden" name="list_type" value="whitelist">
                    <input type="hidden" name="action" value="add">
                    <button type="submit" class="btn">添加</button>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($whitelist['data']['white_list'] ?? [] as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user); ?></td>
                                <td>
                                    <form method="POST" action="index.php" style="display:inline;">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user); ?>">
                                        <input type="hidden" name="list_type" value="whitelist">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 黑名单管理 -->
            <div class="section">
                <h2>黑名单管理</h2>
                
                <form method="POST" action="index.php" class="add-form">
                    <input type="text" name="username" placeholder="输入用户名" required>
                    <input type="hidden" name="list_type" value="blacklist">
                    <input type="hidden" name="action" value="add">
                    <button type="submit" class="btn">添加</button>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blacklist['data']['black_list'] ?? [] as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user); ?></td>
                                <td>
                                    <form method="POST" action="index.php" style="display:inline;">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user); ?>">
                                        <input type="hidden" name="list_type" value="blacklist">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
    <script>
    // AJAX实现无刷新切换（可选）
    document.querySelectorAll('.toggle-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const button = this.querySelector('button');
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 切换按钮状态
                    const isEnabled = data.whitelist_status;
                    button.textContent = isEnabled ? '已启用' : '已禁用';
                    button.className = isEnabled ? 'btn btn-sm btn-success' : 'btn btn-sm btn-danger';
                    // 更新表单中的值
                    this.querySelector('[name="whitelist_status"]').value = isEnabled ? 'disable' : 'enable';
                    
                    // 显示通知
                    showAlert(`白名单已${isEnabled ? '启用' : '禁用'}`, 'success');
                } else {
                    showAlert('操作失败: ' + (data.message || '未知错误'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('请求失败，请检查网络', 'error');
            });
        });
    });
    
    function showAlert(message, type) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        document.querySelector('.container').prepend(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 3000);
    }
    </script>
</html>
