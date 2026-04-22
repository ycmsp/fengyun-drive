<?php
/**
 * 分享页面
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/ShareManager.php';
require_once __DIR__ . '/includes/functions.php';

$shareCode = $_GET['code'] ?? '';

if (empty($shareCode)) {
    header('HTTP/1.1 404 Not Found');
    echo '分享不存在';
    exit;
}

$shareManager = new ShareManager();
$share = $shareManager->getShare($shareCode);

if (!$share) {
    header('HTTP/1.1 404 Not Found');
    echo '分享不存在或已过期';
    exit;
}

$needPassword = $share['share_type'] == 2;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件分享 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="share-page">
    <div class="share-container">
        <div class="share-box">
            <div class="share-header">
                <h1><i class="fas fa-cloud"></i> <?php echo APP_NAME; ?></h1>
            </div>

            <?php if ($needPassword): ?>
            <!-- 密码验证 -->
            <div id="password-section">
                <div class="share-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2>该分享需要密码访问</h2>
                <div class="password-form">
                    <input type="password" id="access-password" placeholder="请输入访问密码" maxlength="4">
                    <button class="btn btn-primary" id="verify-btn">访问文件</button>
                </div>
                <p class="error-msg" id="error-msg"></p>
            </div>

            <!-- 分享内容（验证后显示） -->
            <div id="share-content" style="display: none;">
            <?php else: ?>
            <div id="share-content">
            <?php endif; ?>
                <div class="share-icon">
                    <i class="fas <?php echo $share['is_folder'] ? 'fa-folder' : 'fa-file'; ?>"></i>
                </div>
                <h2 id="share-filename"><?php echo htmlspecialchars($share['filename']); ?></h2>

                <div class="share-info">
                    <div class="info-item">
                        <span class="label">分享者：</span>
                        <span id="share-uploader"><?php echo htmlspecialchars($share['nickname'] ?: $share['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">大小：</span>
                        <span id="share-size"><?php echo $share['is_folder'] ? '-' : formatFileSize($share['file_size']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">分享时间：</span>
                        <span id="share-time"><?php echo $share['created_at']; ?></span>
                    </div>
                    <?php if ($share['expire_at']): ?>
                    <div class="info-item">
                        <span class="label">过期时间：</span>
                        <span id="share-expire"><?php echo $share['expire_at']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($share['is_folder']): ?>
                <!-- 文件夹内容列表 -->
                <div class="folder-contents" id="folder-contents">
                    <table class="file-list">
                        <thead>
                            <tr>
                                <th>文件名</th>
                                <th>大小</th>
                                <th>修改时间</th>
                            </tr>
                        </thead>
                        <tbody id="folder-list-body">
                            <!-- 动态加载 -->
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="share-actions">
                    <a href="api/share.php?action=download&code=<?php echo $shareCode; ?><?php echo $needPassword ? '' : ''; ?>"
                       class="btn btn-primary btn-block" id="download-btn">
                        <i class="fas fa-download"></i> 下载文件
                    </a>
                    <button class="btn btn-secondary btn-block" id="save-btn" style="margin-top: 10px;">
                        <i class="fas fa-save"></i> 转存到我的网盘
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const shareCode = '<?php echo $shareCode; ?>';
        const needPassword = <?php echo $needPassword ? 'true' : 'false'; ?>;
        
        // 转存功能
        document.getElementById('save-btn').addEventListener('click', async function() {
            // 检查是否登录
            const response = await fetch('api/auth.php?action=check');
            const data = await response.json();
            
            if (!data.success) {
                alert('请先登录后再转存文件');
                window.location.href = 'login.php';
                return;
            }
            
            // 转存文件
            try {
                const saveResponse = await fetch('api/share.php?action=save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        share_code: shareCode,
                        parent_id: 0 // 默认保存到根目录
                    })
                });
                
                const saveData = await saveResponse.json();
                
                if (saveData.success) {
                    alert('转存成功！文件已保存到您的网盘');
                } else {
                    alert('转存失败：' + saveData.message);
                }
            } catch (error) {
                alert('转存失败：网络错误');
            }
        });
    </script>
    <script src="assets/js/share.js"></script>
</body>
</html>
