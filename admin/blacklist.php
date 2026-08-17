<?php
/**
 * 黑白名单管理页面
 */
$pageTitle = '黑白名单';
include __DIR__ . '/_header.php';
$list = $admin->getBlacklist();
?>
<div class="blacklist-page">
    <div class="panel">
        <div class="panel-header">
            <h3>添加规则</h3>
        </div>
        <div class="panel-body">
            <form id="addForm" class="form-inline">
                <div class="form-group">
                    <label>类型</label>
                    <select name="type">
                        <option value="ip">IP地址</option>
                        <option value="fingerprint">访客指纹</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>动作</label>
                    <select name="action_type">
                        <option value="block">黑名单(拒绝)</option>
                        <option value="allow">白名单(放行)</option>
                    </select>
                </div>
                <div class="form-group flex-2">
                    <label>值</label>
                    <input type="text" name="value" placeholder="如: 192.168.1.1 或 指纹哈希" required>
                </div>
                <div class="form-group flex-2">
                    <label>备注</label>
                    <input type="text" name="reason" placeholder="可选">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-primary" onclick="addRule()">添加</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>规则列表 (<?= count($list) ?>)</h3>
        </div>
        <div class="panel-body">
            <?php if (empty($list)): ?>
                <div class="empty-state">暂无规则</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>类型</th>
                            <th>值</th>
                            <th>动作</th>
                            <th>备注</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="blacklistBody">
                        <?php foreach ($list as $item): ?>
                        <tr data-id="<?= $item['id'] ?>">
                            <td><?= $item['id'] ?></td>
                            <td><?= $item['type'] === 'ip' ? 'IP地址' : '访客指纹' ?></td>
                            <td class="mono"><?= htmlspecialchars($item['value']) ?></td>
                            <td>
                                <?php if ($item['action'] === 'block'): ?>
                                    <span class="badge badge-red">黑名单</span>
                                <?php else: ?>
                                    <span class="badge badge-green">白名单</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['reason'] ?: '-') ?></td>
                            <td><?= date('Y-m-d H:i:s', $item['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="removeRule(<?= $item['id'] ?>)">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function addRule() {
    const form = document.getElementById('addForm');
    const formData = new FormData(form);
    formData.append('api', 'add_blacklist');

    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || '添加失败');
        }
    });
}

function removeRule(id) {
    if (!confirm('确定删除该规则？')) return;
    const formData = new FormData();
    formData.append('api', 'remove_blacklist');
    formData.append('id', id);

    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(() => location.reload());
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
