/**
 * 分享页面脚本
 */

document.addEventListener('DOMContentLoaded', () => {
    if (needPassword) {
        initPasswordVerify();
    } else {
        loadShareContent();
    }
});

// 初始化密码验证
function initPasswordVerify() {
    const verifyBtn = document.getElementById('verify-btn');
    const passwordInput = document.getElementById('access-password');
    const errorMsg = document.getElementById('error-msg');

    verifyBtn.addEventListener('click', async () => {
        const password = passwordInput.value.trim();

        if (!password) {
            errorMsg.textContent = '请输入密码';
            return;
        }

        try {
            const response = await fetch(`api/share.php?action=verify&code=${shareCode}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            const data = await response.json();

            if (data.success) {
                // 隐藏密码输入，显示内容
                document.getElementById('password-section').style.display = 'none';
                document.getElementById('share-content').style.display = 'block';

                // 更新分享信息
                updateShareInfo(data.data);

                // 如果是文件夹，渲染内容
                if (data.data.is_folder && data.data.files) {
                    renderFolderContents(data.data.files);
                }

                // 更新下载按钮链接
                const downloadBtn = document.getElementById('download-btn');
                downloadBtn.href = `api/share.php?action=download&code=${shareCode}&password=${encodeURIComponent(password)}`;
            } else {
                errorMsg.textContent = data.message || '密码错误';
            }
        } catch (error) {
            errorMsg.textContent = '验证失败，请重试';
        }
    });

    // 回车提交
    passwordInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            verifyBtn.click();
        }
    });
}

// 加载分享内容（不需要密码）
async function loadShareContent() {
    try {
        const response = await fetch(`api/share.php?action=info&code=${shareCode}`);
        const data = await response.json();

        if (data.success) {
            updateShareInfo(data.data);

            // 如果是文件夹，渲染内容
            if (data.data.is_folder && data.data.files) {
                renderFolderContents(data.data.files);
            }
        } else {
            document.querySelector('.share-box').innerHTML = `
                <div class="share-header">
                    <h1><i class="fas fa-cloud"></i> Cloud Drive</h1>
                </div>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #dc3545; margin-bottom: 20px;"></i>
                    <h2>分享不存在或已过期</h2>
                </div>
            `;
        }
    } catch (error) {
        console.error('加载分享失败', error);
    }
}

// 更新分享信息显示
function updateShareInfo(share) {
    document.getElementById('share-filename').textContent = share.filename;
    document.getElementById('share-uploader').textContent = share.uploader;
    document.getElementById('share-size').textContent = share.size_formatted;
    document.getElementById('share-time').textContent = formatDate(share.created_at);

    if (share.expire_at) {
        const expireElem = document.getElementById('share-expire');
        if (expireElem) {
            expireElem.textContent = formatDate(share.expire_at);
        }
    }
}

// 渲染文件夹内容
function renderFolderContents(files) {
    const tbody = document.getElementById('folder-list-body');

    if (files.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align: center; padding: 20px; color: #999;">
                    空文件夹
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = files.map(file => {
        const iconClass = file.is_folder ? 'fa-folder' : getFileIconClass(file.file_extension);
        return `
            <tr>
                <td>
                    <div class="file-name">
                        <i class="fas ${iconClass}" style="color: ${file.is_folder ? '#f4d03f' : '#4a90d9'};"></i>
                        <span>${escapeHtml(file.filename)}</span>
                    </div>
                </td>
                <td>${file.is_folder ? '-' : formatFileSize(file.file_size)}</td>
                <td>${formatDate(file.created_at)}</td>
            </tr>
        `;
    }).join('');
}

// 获取文件图标类
function getFileIconClass(ext) {
    const iconMap = {
        'image': ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'video': ['mp4', 'avi', 'mkv', 'mov', 'wmv'],
        'audio': ['mp3', 'wav', 'flac', 'aac'],
        'pdf': ['pdf'],
        'word': ['doc', 'docx'],
        'excel': ['xls', 'xlsx'],
        'powerpoint': ['ppt', 'pptx'],
        'archive': ['zip', 'rar', '7z'],
        'code': ['js', 'css', 'html', 'php', 'py'],
        'text': ['txt', 'md']
    };

    for (const [icon, exts] of Object.entries(iconMap)) {
        if (exts.includes(ext?.toLowerCase())) {
            return `fa-file-${icon}`;
        }
    }
    return 'fa-file';
}

// 格式化日期
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString('zh-CN');
}

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
