/**
 * 管理后台通用 JS
 */

// AJAX 请求封装
window.rlApi = function(action, data) {
    const formData = new FormData();
    formData.append('api', action);
    if (data) {
        for (let key in data) {
            formData.append(key, data[key]);
        }
    }
    return fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    }).then(r => r.json());
};

// 页面加载完成后的通用初始化
document.addEventListener('DOMContentLoaded', function() {
    // 自动刷新状态指示器
    const statusEl = document.getElementById('liveStatus');
    if (statusEl) {
        setInterval(() => {
            statusEl.style.opacity = '0.3';
            setTimeout(() => { statusEl.style.opacity = '1'; }, 300);
        }, 5000);
    }
});
