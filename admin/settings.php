<?php
/**
 * 参数配置页面
 */
$pageTitle = '参数配置';
include __DIR__ . '/_header.php';

// 获取当前配置
$cfg = [
    'global_rate'       => $admin->getConfig('global_rate', $config['global_rate']),
    'global_burst'      => $admin->getConfig('global_burst', $config['global_burst']),
    'ip_rate'           => $admin->getConfig('ip_rate', $config['ip_rate']),
    'ip_burst'          => $admin->getConfig('ip_burst', $config['ip_burst']),
    'queue_max_size'    => $admin->getConfig('queue_max_size', $config['queue_max_size']),
    'queue_timeout'     => $admin->getConfig('queue_timeout', $config['queue_timeout']),
    'poll_interval'     => $admin->getConfig('poll_interval', $config['poll_interval']),
    'queue_title'       => $admin->getConfig('queue_title', $config['queue_title']),
    'queue_message'     => $admin->getConfig('queue_message', $config['queue_message']),
    'queue_full'        => $admin->getConfig('queue_full', $config['queue_full']),
    'queue_timeout_msg' => $admin->getConfig('queue_timeout_msg', $config['queue_timeout_msg']),
];
?>
<div class="settings-page">
    <form id="settingsForm" class="panel">
        <div class="panel-header">
            <h3>限速参数</h3>
        </div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>全局每秒请求数</label>
                    <input type="number" name="global_rate" value="<?= $cfg['global_rate'] ?>" min="1" required>
                    <small>所有游客每秒总共允许的请求数</small>
                </div>
                <div class="form-group">
                    <label>全局突发容量</label>
                    <input type="number" name="global_burst" value="<?= $cfg['global_burst'] ?>" min="1" required>
                    <small>令牌桶最大容量，允许短时突发</small>
                </div>
                <div class="form-group">
                    <label>单IP每秒请求数</label>
                    <input type="number" name="ip_rate" value="<?= $cfg['ip_rate'] ?>" min="1" required>
                    <small>每个IP每秒允许的请求数</small>
                </div>
                <div class="form-group">
                    <label>单IP突发容量</label>
                    <input type="number" name="ip_burst" value="<?= $cfg['ip_burst'] ?>" min="1" required>
                    <small>单IP令牌桶最大容量</small>
                </div>
            </div>
        </div>
    </form>

    <form id="queueForm" class="panel">
        <div class="panel-header">
            <h3>排队参数</h3>
        </div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>队列最大长度</label>
                    <input type="number" name="queue_max_size" value="<?= $cfg['queue_max_size'] ?>" min="1" required>
                    <small>超出后返回"系统繁忙"</small>
                </div>
                <div class="form-group">
                    <label>排队超时(秒)</label>
                    <input type="number" name="queue_timeout" value="<?= $cfg['queue_timeout'] ?>" min="5" required>
                    <small>超过该时间未被处理则自动移除</small>
                </div>
                <div class="form-group">
                    <label>轮询间隔(秒)</label>
                    <input type="number" name="poll_interval" value="<?= $cfg['poll_interval'] ?>" min="1" max="10" required>
                    <small>前端排队页的轮询频率</small>
                </div>
            </div>
        </div>
    </form>

    <form id="messageForm" class="panel">
        <div class="panel-header">
            <h3>排队页面文案</h3>
        </div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>页面标题</label>
                    <input type="text" name="queue_title" value="<?= htmlspecialchars($cfg['queue_title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>提示信息</label>
                    <input type="text" name="queue_message" value="<?= htmlspecialchars($cfg['queue_message']) ?>" required>
                </div>
                <div class="form-group">
                    <label>队列满提示</label>
                    <input type="text" name="queue_full" value="<?= htmlspecialchars($cfg['queue_full']) ?>" required>
                </div>
                <div class="form-group">
                    <label>超时提示</label>
                    <input type="text" name="queue_timeout_msg" value="<?= htmlspecialchars($cfg['queue_timeout_msg']) ?>" required>
                </div>
            </div>
        </div>
    </form>

    <div class="panel">
        <div class="panel-header">
            <h3>管理账号</h3>
        </div>
        <div class="panel-body">
            <form id="accountForm" class="form-grid">
                <div class="form-group">
                    <label>新用户名</label>
                    <input type="text" name="admin_user" placeholder="留空则不修改">
                </div>
                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" name="admin_pass" placeholder="留空则不修改">
                </div>
            </form>
        </div>
    </div>

    <div class="form-actions">
        <button type="button" class="btn btn-primary btn-lg" onclick="saveAllSettings()">保存所有配置</button>
        <span id="saveStatus" class="save-status"></span>
    </div>
</div>

<script>
function saveAllSettings() {
    const forms = ['settingsForm', 'queueForm', 'messageForm', 'accountForm'];
    const formData = new FormData();
    formData.append('api', 'save_settings');

    forms.forEach(id => {
        const form = document.getElementById(id);
        const data = new FormData(form);
        for (let [key, value] of data.entries()) {
            // 空值不提交（密码留空表示不修改）
            if (value !== '') {
                formData.append(key, value);
            }
        }
    });

    const statusEl = document.getElementById('saveStatus');
    statusEl.textContent = '保存中...';
    statusEl.className = 'save-status saving';

    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusEl.textContent = '✓ 保存成功';
            statusEl.className = 'save-status success';
            setTimeout(() => { statusEl.textContent = ''; }, 3000);
        } else {
            statusEl.textContent = '✗ 保存失败';
            statusEl.className = 'save-status error';
        }
    })
    .catch(() => {
        statusEl.textContent = '✗ 网络错误';
        statusEl.className = 'save-status error';
    });
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
