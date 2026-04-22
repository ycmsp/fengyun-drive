<?php
/**
 * 网盘主页面
 */
// 启用适当的缓存
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
header('Pragma: public');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

// 初始化设置
$settings = new Settings();

$auth = new Auth();

// 检查登录状态
if (!$auth->check()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// 强制重新获取用户信息
$user = $auth->user();
$storagePercent = $user['storage_limit'] > 0 ? round($user['storage_used'] / $user['storage_limit'] * 100, 2) : 0;

// 调试信息
error_log("User directory_browsing: " . (isset($user['directory_browsing']) ? $user['directory_browsing'] : 'not set'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的网盘 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1><i class="fas fa-cloud"></i> <?php echo APP_NAME; ?></h1>
            </div>

            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-view="files">
                    <i class="fas fa-folder"></i>
                    <span>我的文件</span>
                </a>
                <a href="#" class="nav-item" data-view="shares">
                    <i class="fas fa-share-alt"></i>
                    <span>我的分享</span>
                </a>
                <a href="#" class="nav-item" data-view="offline">
                    <i class="fas fa-cloud-download-alt"></i>
                    <span>离线下载</span>
                </a>
                <a href="#" class="nav-item" data-view="profile">
                    <i class="fas fa-user"></i>
                    <span>个人中心</span>
                </a>
                <?php if ($user['is_admin']): ?>
                <a href="admin.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>管理后台</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="storage-info">
                <div class="storage-header">
                    <span>存储空间</span>
                    <span id="storage-text"><?php echo formatFileSize($user['storage_used']); ?> / <?php echo formatFileSize($user['storage_limit']); ?></span>
                </div>
                <div class="storage-bar">
                    <div class="storage-progress" style="width: <?php echo $storagePercent; ?>%"></div>
                </div>
                <div class="storage-percent"><?php echo $storagePercent; ?>%</div>
            </div>

            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="main-content">
            <!-- 文件管理视图 -->
            <div id="files-view" class="view active">
                <div class="toolbar">
                    <div class="toolbar-left">
                        <button class="btn btn-primary" id="upload-btn">
                            <i class="fas fa-upload"></i> 上传文件
                        </button>
                        <button class="btn btn-secondary" id="mkdir-btn">
                            <i class="fas fa-folder-plus"></i> 新建文件夹
                        </button>
                    </div>
                    <div class="toolbar-right">
                        <div class="search-box">
                            <input type="text" id="search-input" placeholder="搜索文件...">
                            <button id="search-btn"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="breadcrumb" id="breadcrumb">
                    <span class="breadcrumb-item" data-id="0">根目录</span>
                </div>

                <div class="file-list-container">
                    <table class="file-list">
                        <thead>
                            <tr>
                                <th class="col-name">文件名</th>
                                <th class="col-progress">进度</th>
                                <th class="col-size">大小</th>
                                <th class="col-md5">MD5</th>
                                <th class="col-date">修改时间</th>
                                <th class="col-actions">操作</th>
                            </tr>
                        </thead>
                        <tbody id="file-list-body">
                            <!-- 文件列表将在这里动态加载 -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 分享管理视图 -->
            <div id="shares-view" class="view">
                <div class="toolbar">
                    <h2>我的分享</h2>
                </div>
                <div class="share-list-container">
                    <table class="file-list">
                        <thead>
                            <tr>
                                <th class="col-name">文件名</th>
                                <th class="col-type">类型</th>
                                <th class="col-code">分享码</th>
                                <th class="col-stats">访问/下载</th>
                                <th class="col-date">过期时间</th>
                                <th class="col-actions">操作</th>
                            </tr>
                        </thead>
                        <tbody id="share-list-body">
                            <!-- 分享列表将在这里动态加载 -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 离线下载视图 -->
            <div id="offline-view" class="view">
                <div class="toolbar">
                    <h2>离线下载</h2>
                    <button class="btn btn-primary" id="add-offline-task">
                        <i class="fas fa-plus"></i> 添加下载任务
                    </button>
                </div>
                <div class="offline-task-list">
                    <table class="file-list">
                        <thead>
                            <tr>
                                <th class="col-name">任务名称</th>
                                <th class="col-url">下载链接</th>
                                <th class="col-status">状态</th>
                                <th class="col-progress">进度</th>
                                <th class="col-size">大小</th>
                                <th class="col-date">创建时间</th>
                                <th class="col-actions">操作</th>
                            </tr>
                        </thead>
                        <tbody id="offline-task-list-body">
                            <!-- 离线下载任务列表将在这里动态加载 -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 个人中心视图 -->
            <div id="profile-view" class="view">
                <div class="profile-container">
                    <h2>个人资料</h2>
                    <form id="profile-form" class="profile-form">
                        <div class="form-group">
                            <label>用户名</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>邮箱</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label>昵称</label>
                            <input type="text" name="nickname" value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="directory_browsing" value="1" <?php echo isset($user['directory_browsing']) && $user['directory_browsing'] ? 'checked' : ''; ?>>
                                启用目录化浏览
                            </label>
                            <p class="form-help">启用后，您可以通过 URL 直接浏览和下载文件</p>
                        </div>
                        <button type="submit" class="btn btn-primary">保存修改</button>
                    </form>

                    <h2 style="margin-top: 30px;">修改密码</h2>
                    <form id="password-form" class="profile-form">
                        <div class="form-group">
                            <label>原密码</label>
                            <input type="password" name="old_password" required>
                        </div>
                        <div class="form-group">
                            <label>新密码</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>确认新密码</label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary">修改密码</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- 上传文件输入框 -->
    <input type="file" id="file-input" multiple style="display: none;">

    <!-- 新建文件夹弹窗 -->
    <div class="modal" id="mkdir-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>新建文件夹</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>文件夹名称</label>
                    <input type="text" id="folder-name" placeholder="请输入文件夹名称">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-cancel">取消</button>
                <button class="btn btn-primary" id="confirm-mkdir">确定</button>
            </div>
        </div>
    </div>

    <!-- 分享弹窗 -->
    <div class="modal" id="share-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>创建分享</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>分享类型</label>
                    <select id="share-type">
                        <option value="1">公开分享</option>
                        <option value="2">密码保护</option>
                        <option value="3">直链分享</option>
                    </select>
                </div>
                <div class="form-group" id="password-group" style="display: none;">
                    <label>访问密码</label>
                    <input type="text" id="share-password" placeholder="设置4位数字密码" maxlength="4">
                </div>
                <div class="form-group">
                    <label>有效期</label>
                    <select id="share-expire">
                        <option value="1">1天</option>
                        <option value="7" selected>7天</option>
                        <option value="30">30天</option>
                        <option value="0">永久有效</option>
                    </select>
                </div>
                <div class="share-result" id="share-result" style="display: none;">
                    <div class="form-group">
                        <label>分享链接</label>
                        <div class="input-group">
                            <input type="text" id="share-url" readonly>
                            <button class="btn btn-secondary" id="copy-url">复制</button>
                        </div>
                    </div>
                    <div class="form-group" id="share-code-group">
                        <label>提取码</label>
                        <input type="text" id="share-code-display" readonly>
                    </div>
                    <div class="form-group">
                        <label>直链分享</label>
                        <div class="input-group">
                            <input type="text" id="direct-url" readonly>
                            <button class="btn btn-secondary" id="copy-direct-url">复制</button>
                        </div>
                        <p class="small-text">直链24小时内有效，无需密码即可直接下载</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-cancel">关闭</button>
                <button class="btn btn-primary" id="confirm-share">创建分享</button>
            </div>
        </div>
    </div>

    <!-- 移动文件弹窗 -->
    <div class="modal" id="move-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>移动到</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="folder-tree" id="folder-tree">
                    <!-- 文件夹树将在这里动态加载 -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-cancel">取消</button>
                <button class="btn btn-primary" id="confirm-move">确定</button>
            </div>
        </div>
    </div>

    <!-- 预览弹窗 -->
    <div class="modal" id="preview-modal">
        <div class="modal-content preview-modal-content">
            <div class="modal-header">
                <h3 id="preview-title">预览</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="preview-content">
                    <!-- 预览内容将在这里动态加载 -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary modal-cancel">关闭</button>
            </div>
        </div>
    </div>

    <!-- 离线下载任务弹窗 -->
    <div class="modal" id="offline-task-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加离线下载任务</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>下载链接</label>
                    <input type="url" id="offline-url" placeholder="请输入下载链接" required>
                </div>
                <div class="form-group">
                    <label>任务名称</label>
                    <input type="text" id="offline-task-name" placeholder="请输入任务名称">
                </div>
                <div class="form-group">
                    <label>保存位置</label>
                    <select id="offline-save-path">
                        <option value="0">根目录</option>
                        <!-- 文件夹选项将在这里动态加载 -->
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-cancel">取消</button>
                <button class="btn btn-primary" id="confirm-offline-task">确定</button>
            </div>
        </div>
    </div>

    <!-- 提示消息 -->
    <div class="toast" id="toast"></div>

    <script src="assets/js/app.js"></script>
</body>
</html>
