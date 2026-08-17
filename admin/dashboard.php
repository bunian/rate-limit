<?php
/**
 * 仪表盘页面
 */
$pageTitle = '仪表盘';
include __DIR__ . '/_header.php';
$data = $admin->getDashboardData();
?>
<div class="dashboard">
    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">👥</div>
            <div class="stat-info">
                <div class="stat-value" id="activeVisitors"><?= $data['active_visitors'] ?></div>
                <div class="stat-label">活跃访客(5分钟)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">⏳</div>
            <div class="stat-info">
                <div class="stat-value" id="queueLength"><?= $data['queue_length'] ?></div>
                <div class="stat-label">排队中</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-info">
                <div class="stat-value" id="todayAllowed"><?= $data['stats']['allowed'] ?></div>
                <div class="stat-label">今日放行</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🚫</div>
            <div class="stat-info">
                <div class="stat-value" id="todayLimited"><?= $data['stats']['rate_limited'] ?></div>
                <div class="stat-label">今日限速</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">📝</div>
            <div class="stat-info">
                <div class="stat-value" id="todayQueued"><?= $data['stats']['queued'] ?></div>
                <div class="stat-label">今日排队</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">⚠️</div>
            <div class="stat-info">
                <div class="stat-value" id="todayRejected"><?= $data['stats']['rejected'] ?></div>
                <div class="stat-label">今日拒绝</div>
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- 当前队列 -->
        <div class="panel">
            <div class="panel-header">
                <h3>当前排队队列</h3>
                <button class="btn btn-sm btn-danger" onclick="clearQueue()">清空队列</button>
            </div>
            <div class="panel-body">
                <div id="queueList" class="queue-list">
                    <?php if (empty($data['queue_list'])): ?>
                        <div class="empty-state">当前无排队请求</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>指纹(前8位)</th><th>接口</th><th>状态</th><th>入队时间</th><th>剩余时间</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['queue_list'] as $idx => $item): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td class="mono"><?= htmlspecialchars(substr($item['fingerprint'], 0, 8)) ?>...</td>
                                    <td><?= htmlspecialchars($item['action']) ?></td>
                                    <td><span class="badge badge-<?= $item['status'] === 'processing' ? 'green' : 'orange' ?>"><?= $item['status'] === 'processing' ? '处理中' : '等待中' ?></span></td>
                                    <td><?= date('H:i:s', $item['enqueued_at']) ?></td>
                                    <td><?= max(0, $item['expires_at'] - time()) ?>秒</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 最近日志 -->
        <div class="panel">
            <div class="panel-header">
                <h3>最近事件日志</h3>
                <a href="index.php?page=logs" class="btn btn-sm">查看全部</a>
            </div>
            <div class="panel-body">
                <div id="recentLogs">
                    <?php if (empty($data['recent_logs'])): ?>
                        <div class="empty-state">暂无日志</div>
                    <?php else: ?>
                        <div class="log-list">
                            <?php foreach ($data['recent_logs'] as $log): ?>
                            <div class="log-item">
                                <span class="log-time"><?= date('H:i:s', $log['created_at']) ?></span>
                                <span class="log-event event-<?= $log['event'] ?>"><?= htmlspecialchars($log['event']) ?></span>
                                <span class="log-ip mono"><?= htmlspecialchars($log['ip']) ?></span>
                                <span class="log-detail"><?= htmlspecialchars($log['detail']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 当前配置概览 -->
    <div class="panel">
        <div class="panel-header">
            <h3>当前限速配置</h3>
            <a href="index.php?page=settings" class="btn btn-sm">修改配置</a>
        </div>
        <div class="panel-body">
            <div class="config-overview">
                <div class="config-item">
                    <span class="config-label">全局速率</span>
                    <span class="config-value"><?= $data['config']['global_rate'] ?> 请求/秒 (突发 <?= $data['config']['global_burst'] ?>)</span>
                </div>
                <div class="config-item">
                    <span class="config-label">单IP速率</span>
                    <span class="config-value"><?= $data['config']['ip_rate'] ?> 请求/秒 (突发 <?= $data['config']['ip_burst'] ?>)</span>
                </div>
                <div class="config-item">
                    <span class="config-label">队列容量</span>
                    <span class="config-value"><?= $data['config']['queue_max_size'] ?> 个请求</span>
                </div>
                <div class="config-item">
                    <span class="config-label">排队超时</span>
                    <span class="config-value"><?= $data['config']['queue_timeout'] ?> 秒</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 仪表盘自动刷新
let dashboardTimer = setInterval(loadDashboard, 5000);

function loadDashboard() {
    fetch('index.php?api=dashboard', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            document.getElementById('activeVisitors').textContent = data.active_visitors;
            document.getElementById('queueLength').textContent = data.queue_length;
            document.getElementById('todayAllowed').textContent = data.stats.allowed;
            document.getElementById('todayLimited').textContent = data.stats.rate_limited;
            document.getElementById('todayQueued').textContent = data.stats.queued;
            document.getElementById('todayRejected').textContent = data.stats.rejected;

            // 更新队列列表
            const queueDiv = document.getElementById('queueList');
            if (data.queue_list.length === 0) {
                queueDiv.innerHTML = '<div class="empty-state">当前无排队请求</div>';
            } else {
                let html = '<table class="data-table"><thead><tr><th>#</th><th>指纹(前8位)</th><th>接口</th><th>状态</th><th>入队时间</th><th>剩余时间</th></tr></thead><tbody>';
                const now = Math.floor(Date.now() / 1000);
                data.queue_list.forEach((item, idx) => {
                    const statusBadge = item.status === 'processing'
                        ? '<span class="badge badge-green">处理中</span>'
                        : '<span class="badge badge-orange">等待中</span>';
                    const remaining = Math.max(0, item.expires_at - now);
                    html += `<tr><td>${idx+1}</td><td class="mono">${item.fingerprint.substring(0,8)}...</td><td>${item.action}</td><td>${statusBadge}</td><td>${new Date(item.enqueued_at*1000).toLocaleTimeString()}</td><td>${remaining}秒</td></tr>`;
                });
                html += '</tbody></table>';
                queueDiv.innerHTML = html;
            }

            // 更新日志
            const logsDiv = document.getElementById('recentLogs');
            if (data.recent_logs.length === 0) {
                logsDiv.innerHTML = '<div class="empty-state">暂无日志</div>';
            } else {
                let html = '<div class="log-list">';
                data.recent_logs.forEach(log => {
                    const time = new Date(log.created_at * 1000).toLocaleTimeString();
                    html += `<div class="log-item"><span class="log-time">${time}</span><span class="log-event event-${log.event}">${log.event}</span><span class="log-ip mono">${log.ip}</span><span class="log-detail">${log.detail || ''}</span></div>`;
                });
                html += '</div>';
                logsDiv.innerHTML = html;
            }
        })
        .catch(() => {});
}

function clearQueue() {
    if (!confirm('确定要清空所有排队请求吗？')) return;
    const form = new FormData();
    form.append('api', 'clear_queue');
    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: form
    }).then(r => r.json()).then(() => loadDashboard());
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
