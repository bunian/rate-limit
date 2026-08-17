<?php
/**
 * 日志查询页面
 */
$pageTitle = '日志查询';
include __DIR__ . '/_header.php';

$event = $_GET['event'] ?? '';
$ip = $_GET['ip'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;

$logs = $admin->queryLogs($event ?: null, $ip ?: null, $startDate ?: null, $endDate ?: null, $page, $perPage);
?>
<div class="logs-page">
    <div class="panel">
        <div class="panel-header">
            <h3>筛选条件</h3>
        </div>
        <div class="panel-body">
            <form method="GET" class="form-inline">
                <input type="hidden" name="page" value="logs">
                <div class="form-group">
                    <label>事件类型</label>
                    <select name="event">
                        <option value="">全部</option>
                        <option value="allowed" <?= $event === 'allowed' ? 'selected' : '' ?>>放行</option>
                        <option value="rate_limited" <?= $event === 'rate_limited' ? 'selected' : '' ?>>限速</option>
                        <option value="queued" <?= $event === 'queued' ? 'selected' : '' ?>>排队</option>
                        <option value="timeout" <?= $event === 'timeout' ? 'selected' : '' ?>>超时</option>
                        <option value="rejected" <?= $event === 'rejected' ? 'selected' : '' ?>>拒绝</option>
                        <option value="redeemed" <?= $event === 'redeemed' ? 'selected' : '' ?>> redeem放行</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>IP</label>
                    <input type="text" name="ip" value="<?= htmlspecialchars($ip) ?>" placeholder="如 192.168.1.1">
                </div>
                <div class="form-group">
                    <label>开始日期</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="form-group">
                    <label>结束日期</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">查询</button>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn" onclick="cleanupLogs()">清理30天前</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>日志列表 (第 <?= $page ?> 页，每页 <?= $perPage ?> 条)</h3>
        </div>
        <div class="panel-body">
            <?php if (empty($logs)): ?>
                <div class="empty-state">无符合条件的日志</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>时间</th>
                            <th>事件</th>
                            <th>IP</th>
                            <th>接口</th>
                            <th>指纹(前12位)</th>
                            <th>详情</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['id'] ?></td>
                            <td><?= date('Y-m-d H:i:s', $log['created_at']) ?></td>
                            <td><span class="log-event event-<?= $log['event'] ?>"><?= htmlspecialchars($log['event']) ?></span></td>
                            <td class="mono"><?= htmlspecialchars($log['ip']) ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td class="mono"><?= htmlspecialchars(substr($log['fingerprint'], 0, 12)) ?>...</td>
                            <td><?= htmlspecialchars($log['detail'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page - 1])) ?>" class="btn btn-sm">上一页</a>
                    <?php endif; ?>
                    <span class="page-info">第 <?= $page ?> 页</span>
                    <?php if (count($logs) >= $perPage): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page + 1])) ?>" class="btn btn-sm">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function cleanupLogs() {
    if (!confirm('确定要清理30天前的所有日志吗？此操作不可恢复。')) return;
    const formData = new FormData();
    formData.append('api', 'cleanup_logs');
    formData.append('days', '30');

    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        alert('已清理 ' + (data.cleaned || 0) + ' 条日志');
        location.reload();
    });
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
