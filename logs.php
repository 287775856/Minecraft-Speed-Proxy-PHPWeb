<?php
session_start();
require_once 'api-helper.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$token = getToken();

// 默认查询最近1小时的日志
$endTime = time();
$startTime = $endTime - 3600;
$granularity = 'minute';

// 处理查询参数
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    $startTime = strtotime($_GET['start_time']) ?: $startTime;
    $endTime = strtotime($_GET['end_time']) ?: $endTime;
    $granularity = in_array($_GET['granularity'], ['minute', 'hour', 'day']) 
        ? $_GET['granularity'] 
        : 'minute';
}

// 获取日志数据
$logs = makeApiRequest('get_logs', 'GET', null, $token);
$onlineStats = makeApiRequest('get_online_number_list', 'POST', [
    'start_time' => $startTime,
    'end_time' => $endTime,
    'granularity' => $granularity
], $token);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 代理服务器 - 日志查询</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Minecraft 代理服务器日志</h1>
            <div>
                <a href="index.php" class="btn">返回控制面板</a>
                <a href="logout.php" class="logout-btn">退出</a>
            </div>
        </header>
        
        <!-- 查询表单 -->
        <div class="section">
            <h2>日志查询</h2>
            <form method="GET" action="logs.php" class="log-query-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">开始时间:</label>
                        <input type="datetime-local" id="start_time" name="start_time" 
                               value="<?php echo date('Y-m-d\TH:i', $startTime); ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_time">结束时间:</label>
                        <input type="datetime-local" id="end_time" name="end_time" 
                               value="<?php echo date('Y-m-d\TH:i', $endTime); ?>">
                    </div>
                    <div class="form-group">
                        <label for="granularity">统计粒度:</label>
                        <select id="granularity" name="granularity">
                            <option value="minute" <?php echo $granularity === 'minute' ? 'selected' : ''; ?>>每分钟</option>
                            <option value="hour" <?php echo $granularity === 'hour' ? 'selected' : ''; ?>>每小时</option>
                            <option value="day" <?php echo $granularity === 'day' ? 'selected' : ''; ?>>每天</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="query" class="btn">查询</button>
            </form>
        </div>
        
        <!-- 在线用户统计图表 -->
        <div class="section">
            <h2>在线用户统计</h2>
            <div class="chart-container">
                <canvas id="onlineChart"></canvas>
            </div>
        </div>
        
        <!-- 日志列表 -->
        <div class="section">
            <h2>日志记录</h2>
            <div class="log-list">
                <?php foreach ($logs['data']['logs'] ?? [] as $log): ?>
                    <div class="log-entry">
                        
                        <div class="log-message"><div class="log-time"><?php echo date('Y-m-d H:i:s', $log['timestamp']); ?></div><?php echo htmlspecialchars($log['message']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        // 绘制在线用户图表
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('onlineChart').getContext('2d');
            const statsData = <?php echo json_encode($onlineStats['data']['user_numbers'] ?? []); ?>;
            
            const labels = statsData.map(item => {
                const date = new Date(item.timestamp * 1000);
                return date.toLocaleString();
            });
            
            const data = statsData.map(item => item.online_users);
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '在线用户数',
                        data: data,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>