<?php
/**
 * 目录化浏览入口文件
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/FileManager.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/functions.php';

// 初始化设置
$settings = new Settings();

// 获取 URL 路径
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($path, '/');



// 分割路径
$pathParts = explode('/', $path);

// 处理用户 ID
if (isset($pathParts[0]) && is_numeric($pathParts[0])) {
    $userId = intval($pathParts[0]);
    array_shift($pathParts);
    
    // 检查用户是否存在
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE id = ? AND status = 1", [$userId]);
    if (!$user) {
        header('HTTP/1.1 404 Not Found');
        include __DIR__ . '/404.php';
        exit;
    }
    
    // 检查目录化浏览是否启用
    if (!$user['directory_browsing']) {
        header('HTTP/1.1 404 Not Found');
        include __DIR__ . '/404.php';
        exit;
    }
    
    // 检查公开访问是否允许
    if (!$settings->isPublicAccessAllowed()) {
        $auth = new Auth();
        if (!$auth->check() || $auth->id() != $userId) {
            header('HTTP/1.1 403 Forbidden');
            echo '无权访问';
            exit;
        }
    }
    
    // 构建文件路径
    $filePath = implode('/', $pathParts);
    
    // 如果是空路径，显示用户根目录
    if (empty($filePath)) {
        displayDirectory($userId, 0);
    } else {
        // 查找文件或文件夹
        $fileManager = new FileManager();
        $file = findFileByPath($userId, $filePath);
        
        if ($file) {
            if ($file['is_folder']) {
                // 显示文件夹内容
                displayDirectory($userId, $file['id']);
            } else {
                // 检查文件类型，决定是在线打开还是下载
                $filePath = $fileManager->getFilePath($file);
                if (file_exists($filePath)) {
                    $fileExtension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                    
                    // 支持在线打开的文件类型
            $previewableTypes = [
                // 图片
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
                // 文本
                'txt', 'log', 'md', 'json', 'xml', 'html', 'css', 'js',
                // 音频
                'mp3', 'wav', 'ogg', 'aac', 'flac',
                // 视频
                'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'
            ];
            
            // 检查是否启用Office预览
            $enableOfficePreview = $settings->get('enable_office_preview', 1);
            if ($enableOfficePreview) {
                // 添加Office文件类型
                $previewableTypes = array_merge($previewableTypes, [
                    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
                ]);
            }
                    
                    if (in_array($fileExtension, $previewableTypes)) {
                        // 在线打开文件
                        displayFilePreview($userId, $file);
                    } else {
                        // 下载文件
                        downloadFile($filePath, $file['original_name']);
                    }
                } else {
                    header('HTTP/1.1 404 Not Found');
                    include __DIR__ . '/404.php';
                    exit;
                }
            }
        } else {
            header('HTTP/1.1 404 Not Found');
            include __DIR__ . '/404.php';
            exit;
        }
    }
} else {
    // 显示登录页面或其他内容
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/**
 * 根据路径查找文件
 */
function findFileByPath($userId, $path) {
    global $db;
    
    $pathParts = explode('/', $path);
    $currentParentId = 0;
    $file = null;
    
    foreach ($pathParts as $part) {
        $file = $db->fetch(
            "SELECT * FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND status = 1",
            [$userId, $currentParentId, $part]
        );
        
        if (!$file) {
            return null;
        }
        
        $currentParentId = $file['id'];
    }
    
    return $file;
}

/**
 * 显示目录内容
 */
function displayDirectory($userId, $parentId) {
    global $db, $settings;
    
    // 获取分页参数
    $page = intval($_GET['page'] ?? 1);
    // 从设置中获取每页显示数量，默认为20
    $limit = intval($settings->get('directory_browsing_per_page', 20));
    
    $fileManager = new FileManager();
    $result = $fileManager->getFiles($userId, $parentId, $page, $limit);
    $files = $result['files'];
    $breadcrumbs = $result['breadcrumbs'];
    $total = $result['total'];
    $pages = $result['pages'];
    
    // 获取用户信息
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    
    // 构建基础路径
    $basePath = '';
    
    // 构建面包屑导航
    $breadcrumbHtml = '<nav aria-label="breadcrumb">';
    $breadcrumbHtml .= '<ol class="breadcrumb">';
    $breadcrumbHtml .= '<li class="breadcrumb-item"><a href="/' . $userId . '">根目录</a></li>';
    
    foreach ($breadcrumbs as $breadcrumb) {
        if ($breadcrumb['id'] != $parentId) {
            $breadcrumbHtml .= '<li class="breadcrumb-item"><a href="/' . $userId . '/' . getPathFromBreadcrumb($breadcrumbs, $breadcrumb['id']) . '">' . htmlspecialchars($breadcrumb['name']) . '</a></li>';
        } else {
            $breadcrumbHtml .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($breadcrumb['name']) . '</li>';
        }
    }
    
    $breadcrumbHtml .= '</ol>';
    $breadcrumbHtml .= '</nav>';
    
    // 构建文件列表
    $fileListHtml = '<ul class="file-list">';
    
    // 添加返回上级目录链接
    if ($parentId > 0) {
        $parentPath = getParentPath($userId, $parentId);
        $fileListHtml .= '<li class="file-item folder-item">';
        $fileListHtml .= '<a href="/' . $userId . '/' . $parentPath . '">';
        $fileListHtml .= '<i class="fas fa-folder"></i>';
        $fileListHtml .= '<span>..</span>';
        $fileListHtml .= '</a>';
        $fileListHtml .= '</li>';
    }
    
    foreach ($files as $file) {
        $fileUrl = '/' . $userId . '/' . getFilePath($userId, $file['id']);
        $fileListHtml .= '<li class="file-item ' . ($file['is_folder'] ? 'folder-item' : 'file-item') . '">';
        $fileListHtml .= '<a href="' . $fileUrl . '">';
        $fileListHtml .= '<i class="fas ' . ($file['is_folder'] ? 'fa-folder' : 'fa-file') . '"></i>';
        $fileListHtml .= '<span>' . htmlspecialchars($file['filename']) . '</span>';
        if (!$file['is_folder']) {
            $fileListHtml .= '<span class="file-size">' . formatFileSize($file['file_size']) . '</span>';
        }
        $fileListHtml .= '</a>';
        $fileListHtml .= '</li>';
    }
    
    $fileListHtml .= '</ul>';
    
    // 构建文件列表
    $fileListHtml = '<div class="file-list">';
    
    // 添加返回上级目录链接
    if ($parentId > 0) {
        $parentPath = getParentPath($userId, $parentId);
        $fileListHtml .= '<div class="file-item folder-item">';
        $fileListHtml .= '<a href="/' . $userId . '/' . $parentPath . '">';
        $fileListHtml .= '<i class="fas fa-arrow-up"></i>';
        $fileListHtml .= '<div class="file-info">';
        $fileListHtml .= '<div class="file-name">..</div>';
        $fileListHtml .= '<div class="file-meta">';
        $fileListHtml .= '<span><i class="fas fa-level-up-alt"></i> 上级目录</span>';
        $fileListHtml .= '</div>';
        $fileListHtml .= '</div>';
        $fileListHtml .= '</a>';
        $fileListHtml .= '</div>';
    }
    
    foreach ($files as $file) {
        $fileUrl = '/' . $userId . '/' . getFilePath($userId, $file['id']);
        $fileExtension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
        $fileTypeClass = $file['is_folder'] ? 'folder-item' : 'file-item ' . $fileExtension;
        $iconClass = $file['is_folder'] ? 'fa-folder' : 'fa-file';
        
        $fileListHtml .= '<div class="file-item ' . $fileTypeClass . '">';
        $fileListHtml .= '<a href="' . $fileUrl . '">';
        $fileListHtml .= '<i class="fas ' . $iconClass . '"></i>';
        $fileListHtml .= '<div class="file-info">';
        $fileListHtml .= '<div class="file-name">' . htmlspecialchars($file['filename']) . '</div>';
        $fileListHtml .= '<div class="file-meta">';
        if (!$file['is_folder']) {
            $fileListHtml .= '<span><i class="fas fa-file-size"></i> ' . formatFileSize($file['file_size']) . '</span>';
            $fileListHtml .= '<span><i class="fas fa-clock"></i> ' . date('Y-m-d H:i', strtotime($file['updated_at'])) . '</span>';
        } else {
            $fileListHtml .= '<span><i class="fas fa-folder"></i> 文件夹</span>';
            $fileListHtml .= '<span><i class="fas fa-clock"></i> ' . date('Y-m-d H:i', strtotime($file['updated_at'])) . '</span>';
        }
        $fileListHtml .= '</div>';
        $fileListHtml .= '</div>';
        $fileListHtml .= '</a>';
        $fileListHtml .= '</div>';
    }
    
    $fileListHtml .= '</div>';
        
        // 构建分页导航
        $paginationHtml = '';
        if ($pages > 1) {
            $paginationHtml = '<div class="pagination">';
            
            // 上一页
            if ($page > 1) {
                $prevPage = $page - 1;
                $paginationHtml .= '<a href="/' . $userId . '/' . getPathFromBreadcrumb($breadcrumbs, $parentId) . '?page=' . $prevPage . '" class="pagination-link">上一页</a>';
            }
            
            // 页码
            for ($i = 1; $i <= $pages; $i++) {
                if ($i == $page) {
                    $paginationHtml .= '<span class="pagination-link active">' . $i . '</span>';
                } else {
                    $paginationHtml .= '<a href="/' . $userId . '/' . getPathFromBreadcrumb($breadcrumbs, $parentId) . '?page=' . $i . '" class="pagination-link">' . $i . '</a>';
                }
            }
            
            // 下一页
            if ($page < $pages) {
                $nextPage = $page + 1;
                $paginationHtml .= '<a href="/' . $userId . '/' . getPathFromBreadcrumb($breadcrumbs, $parentId) . '?page=' . $nextPage . '" class="pagination-link">下一页</a>';
            }
            
            $paginationHtml .= '</div>';
        }
        
        // 输出 HTML
        echo <<<HTML
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>文件浏览 - {$user['username']}</title>
        <link rel="stylesheet" href="/assets/css/all.min.css">
        <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
        <style>
            :root {
                --primary-color: #3498db;
                --secondary-color: #2ecc71;
                --accent-color: #f39c12;
                --text-color: #333;
                --text-light: #666;
                --bg-color: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --card-bg: rgba(255, 255, 255, 0.95);
                --border-color: rgba(224, 229, 236, 0.8);
                --hover-color: rgba(240, 244, 248, 0.9);
                --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                --shadow-hover: 0 10px 15px rgba(0, 0, 0, 0.1);
                --border-radius: 8px;
                --transition: all 0.3s ease;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: var(--bg-color);
                background-attachment: fixed;
                color: var(--text-color);
                line-height: 1.6;
                min-height: 100vh;
                padding: 0;
                margin: 0;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 40px 20px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 40px;
                padding: 20px 0;
                border-bottom: 1px solid var(--border-color);
            }
            
            .header h1 {
                font-size: 2.5rem;
                font-weight: 700;
                color: white;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .header h1 i {
                margin-right: 15px;
                font-size: 2.8rem;
                color: white;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .header p {
                font-size: 1.1rem;
                color: rgba(255, 255, 255, 0.9);
                text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
                margin-top: 10px;
            }
            
            .content-card {
                background-color: var(--card-bg);
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                overflow: hidden;
                transition: var(--transition);
            }
            
            .content-card:hover {
                box-shadow: var(--shadow-hover);
                transform: translateY(-2px);
            }
            
            .card-header {
                background-color: var(--primary-color);
                color: white;
                padding: 20px;
                border-bottom: 1px solid var(--border-color);
            }
            
            .card-header h2 {
                font-size: 1.5rem;
                font-weight: 600;
                margin: 0;
            }
            
            .card-body {
                padding: 30px;
            }
            
            .breadcrumb {
                background-color: rgba(245, 247, 250, 0.9);
                padding: 15px 20px;
                border-radius: var(--border-radius);
                margin-bottom: 30px;
                font-size: 0.95rem;
                border: 1px solid var(--border-color);
            }
            
            .breadcrumb .breadcrumb-item {
                font-weight: 500;
                color: var(--text-color);
            }
            
            .breadcrumb .breadcrumb-item a {
                color: var(--primary-color);
                text-decoration: none;
                transition: var(--transition);
            }
            
            .breadcrumb .breadcrumb-item a:hover {
                color: var(--secondary-color);
                text-decoration: underline;
            }
            
            .file-list {
                margin-top: 30px;
            }
            
            .file-item {
                background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius);
                margin-bottom: 10px;
                transition: var(--transition);
                box-shadow: var(--shadow);
            }
            
            .file-item {
                background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius);
                margin-bottom: 10px;
                transition: var(--transition);
                box-shadow: var(--shadow);
                overflow: hidden;
            }
            
            .file-item:hover {
                box-shadow: var(--shadow-hover);
                transform: translateY(-2px);
                border-color: var(--primary-color);
            }
            
            .file-item a {
                display: flex;
                align-items: center;
                text-decoration: none;
                color: var(--text-color);
                padding: 15px 20px;
                transition: var(--transition);
            }
            
            .file-item:hover a {
                background-color: rgba(52, 152, 219, 0.05);
            }
            
            .file-item i {
                font-size: 1.8rem;
                margin-right: 15px;
                color: var(--accent-color);
                transition: var(--transition);
                width: 40px;
                text-align: center;
            }
            
            .file-item:hover i {
                color: var(--primary-color);
                transform: scale(1.1) rotate(5deg);
            }
            
            .file-item .file-info {
                flex: 1;
            }
            
            .file-item .file-name {
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 5px;
                transition: var(--transition);
            }
            
            .file-item:hover .file-name {
                color: var(--primary-color);
            }
            
            .file-item .file-meta {
                font-size: 0.85rem;
                color: var(--text-light);
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .file-item .file-meta span {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .file-item .file-meta i {
                font-size: 0.8rem;
                margin-right: 0;
                transform: none;
            }
            
            .folder-item i {
                color: var(--accent-color);
            }
            
            .folder-item:hover i {
                color: var(--primary-color);
            }
            
            .file-item.file-item i {
                color: #95a5a6;
            }
            
            .file-item.file-item:hover i {
                color: var(--primary-color);
            }
            
            /* 文件类型图标 */
            .file-item.pdf i {
                color: #e74c3c;
            }
            
            .file-item.doc i, .file-item.docx i {
                color: #3498db;
            }
            
            .file-item.xls i, .file-item.xlsx i {
                color: #2ecc71;
            }
            
            .file-item.ppt i, .file-item.pptx i {
                color: #f39c12;
            }
            
            .file-item.jpg i, .file-item.jpeg i, .file-item.png i, .file-item.gif i {
                color: #9b59b6;
            }
            
            .file-item.mp3 i, .file-item.wav i, .file-item.ogg i {
                color: #1abc9c;
            }
            
            .file-item.mp4 i, .file-item.webm i, .file-item.avi i, .file-item.mkv i {
                color: #e67e22;
            }
            
            .file-item.txt i, .file-item.log i {
                color: #7f8c8d;
            }
            
            .file-item.html i, .file-item.css i, .file-item.js i {
                color: #34495e;
            }
            
            .back-link {
                display: inline-block;
                background-color: var(--primary-color);
                color: white;
                padding: 10px 20px;
                border-radius: var(--border-radius);
                text-decoration: none;
                transition: var(--transition);
                margin-bottom: 20px;
                font-weight: 500;
            }
            
            .back-link:hover {
                background-color: var(--secondary-color);
                transform: translateY(-2px);
                box-shadow: var(--shadow);
            }
            
            .back-link i {
                margin-right: 8px;
            }
            
            /* 分页导航 */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid var(--border-color);
            }
            
            .pagination-link {
                display: inline-block;
                padding: 8px 16px;
                border-radius: var(--border-radius);
                text-decoration: none;
                color: var(--text-color);
                background-color: rgba(255, 255, 255, 0.8);
                border: 1px solid var(--border-color);
                transition: var(--transition);
                font-weight: 500;
            }
            
            .pagination-link:hover {
                background-color: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
                transform: translateY(-2px);
                box-shadow: var(--shadow);
            }
            
            .pagination-link.active {
                background-color: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
                font-weight: 600;
            }
            
            @media (max-width: 768px) {
                .container {
                    padding: 20px 15px;
                }
                
                .header h1 {
                    font-size: 2rem;
                }
                
                .file-grid {
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 15px;
                }
                
                .card-body {
                    padding: 20px;
                }
            }
            
            @media (max-width: 480px) {
                .file-grid {
                    grid-template-columns: 1fr;
                }
                
                .header h1 {
                    font-size: 1.8rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-cloud"></i> {$user['username']} 的文件</h1>
                <p>欢迎访问 {$user['username']} 的文件共享空间</p>
            </div>
            
            <div class="content-card">
                <div class="card-body">
                    {$breadcrumbHtml}
                    {$fileListHtml}
                    {$paginationHtml}
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * 获取文件的完整路径
 */
function getFilePath($userId, $fileId) {
    global $db;
    
    $path = [];
    $currentId = $fileId;
    
    while ($currentId > 0) {
        $file = $db->fetch("SELECT filename, parent_id FROM files WHERE id = ? AND user_id = ?", [$currentId, $userId]);
        if (!$file) {
            break;
        }
        $path[] = $file['filename'];
        $currentId = $file['parent_id'];
    }
    
    return implode('/', array_reverse($path));
}

/**
 * 从面包屑获取路径
 */
function getPathFromBreadcrumb($breadcrumbs, $targetId) {
    $path = [];
    foreach ($breadcrumbs as $breadcrumb) {
        $path[] = $breadcrumb['name'];
        if ($breadcrumb['id'] == $targetId) {
            break;
        }
    }
    return implode('/', $path);
}

/**
 * 获取上级目录路径
 */
function getParentPath($userId, $fileId) {
    global $db;
    
    $file = $db->fetch("SELECT parent_id FROM files WHERE id = ? AND user_id = ?", [$fileId, $userId]);
    if (!$file) {
        return '';
    }
    
    if ($file['parent_id'] == 0) {
        return '';
    }
    
    return getFilePath($userId, $file['parent_id']);
}

/**
 * 显示文件预览
 */
function displayFilePreview($userId, $file) {
    global $db;
    
    $fileManager = new FileManager();
    $filePath = $fileManager->getFilePath($file);
    $fileExtension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
    
    // 构建返回链接
    $parentPath = getParentPath($userId, $file['id']);
    $backUrl = '/' . $userId . '/' . $parentPath;
    
    // 根据文件类型生成预览
    $previewHtml = '';
    
    // 图片预览
    if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
        // 使用相对路径访问文件
        $relativePath = str_replace(APP_ROOT, '', $filePath);
        $previewHtml = '<div class="preview-container">
            <img src="' . $relativePath . '" alt="' . htmlspecialchars($file['filename']) . '" class="preview-image">
        </div>';
    }
    // 文本预览
    elseif (in_array($fileExtension, ['txt', 'log', 'md', 'json', 'xml', 'html', 'css', 'js'])) {
        $content = file_get_contents($filePath);
        $content = htmlspecialchars($content);
        $previewHtml = '<div class="preview-container">
            <pre class="preview-text">' . $content . '</pre>
        </div>';
    }
    // 音频预览
    elseif (in_array($fileExtension, ['mp3', 'wav', 'ogg', 'aac', 'flac'])) {
        // 使用相对路径访问文件
        $relativePath = str_replace(APP_ROOT, '', $filePath);
        $fullUrl = APP_URL . $relativePath;
        $previewHtml = '<div class="preview-container">
            <audio controls class="preview-audio">
                <source src="' . $relativePath . '" type="audio/' . $fileExtension . '">
                您的浏览器不支持音频播放。
            </audio>
        </div>';
    }
    // 视频预览
    elseif (in_array($fileExtension, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'])) {
        // 使用相对路径访问文件
        $relativePath = str_replace(APP_ROOT, '', $filePath);
        $fullUrl = APP_URL . $relativePath;
        // 使用 v.jiasu7.top 播放器
        $previewHtml = '<div class="preview-container">
            <iframe src="https://v.jiasu7.top/jx.php?url=' . urlencode($fullUrl) . '" 
                    width="100%" height="500px" frameborder="0" allowfullscreen></iframe>
        </div>';
    }
    // Office文件预览
    elseif (in_array($fileExtension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
        // 使用相对路径访问文件
        $relativePath = str_replace(APP_ROOT, '', $filePath);
        $fullUrl = APP_URL . $relativePath;
        // 使用 Microsoft Office Online 预览
        $previewHtml = '<div class="preview-container">
            <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($fullUrl) . '" 
                    width="100%" height="600px" frameborder="0"></iframe>
        </div>';
    }
    
    // 构建文件信息
    $fileInfoHtml = '<div class="file-info">
        <h3>' . htmlspecialchars($file['filename']) . '</h3>
        <p>大小: ' . formatFileSize($file['file_size']) . '</p>
        <p>修改时间: ' . date('Y-m-d H:i:s', strtotime($file['updated_at'])) . '</p>
        <div class="preview-actions">
            <a href="' . $backUrl . '" class="back-link"><i class="fas fa-arrow-left"></i> 返回</a>
            <a href="/api/files.php?action=download&id=' . $file['id'] . '" class="download-link"><i class="fas fa-download"></i> 下载</a>
        </div>
    </div>';
    
    // 输出 HTML
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>文件预览 - {$file['filename']}</title>
        <link rel="stylesheet" href="/assets/css/all.min.css">
        <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
        <style>
            :root {
                --primary-color: #3498db;
                --secondary-color: #2ecc71;
                --accent-color: #f39c12;
                --text-color: #333;
                --text-light: #666;
                --bg-color: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --card-bg: rgba(255, 255, 255, 0.95);
                --border-color: rgba(224, 229, 236, 0.8);
                --hover-color: rgba(240, 244, 248, 0.9);
                --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                --shadow-hover: 0 10px 15px rgba(0, 0, 0, 0.1);
                --border-radius: 8px;
                --transition: all 0.3s ease;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: var(--bg-color);
                background-attachment: fixed;
                color: var(--text-color);
                line-height: 1.6;
                min-height: 100vh;
                padding: 0;
                margin: 0;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 40px 20px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 40px;
                padding: 20px 0;
                border-bottom: 1px solid var(--border-color);
            }
            
            .header h1 {
                font-size: 2.5rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .header h1 i {
                margin-right: 15px;
                font-size: 2.8rem;
            }
            
            .content-card {
                background-color: var(--card-bg);
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                overflow: hidden;
                transition: var(--transition);
                margin-bottom: 30px;
            }
            
            .content-card:hover {
                box-shadow: var(--shadow-hover);
                transform: translateY(-2px);
            }
            
            .card-body {
                padding: 30px;
            }
            
            .preview-container {
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 40px;
                background-color: var(--hover-color);
                border-radius: var(--border-radius);
                margin-bottom: 30px;
                min-height: 400px;
            }
            
            .preview-image {
                max-width: 100%;
                max-height: 600px;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
            }
            
            .preview-text {
                width: 100%;
                max-height: 600px;
                overflow: auto;
                padding: 20px;
                background-color: var(--card-bg);
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                font-family: 'Courier New', Courier, monospace;
                font-size: 14px;
                line-height: 1.5;
            }
            
            .preview-audio {
                width: 100%;
                max-width: 600px;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
            }
            
            .preview-video {
                width: 100%;
                max-width: 800px;
                max-height: 600px;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
            }
            
            .file-info {
                background-color: var(--hover-color);
                padding: 20px;
                border-radius: var(--border-radius);
            }
            
            .file-info h3 {
                color: var(--primary-color);
                margin-bottom: 15px;
            }
            
            .file-info p {
                margin-bottom: 10px;
                color: var(--text-light);
            }
            
            .preview-actions {
                margin-top: 20px;
                display: flex;
                gap: 10px;
            }
            
            .back-link, .download-link {
                display: inline-block;
                padding: 10px 20px;
                border-radius: var(--border-radius);
                text-decoration: none;
                transition: var(--transition);
                font-weight: 500;
                display: flex;
                align-items: center;
            }
            
            .back-link {
                background-color: var(--primary-color);
                color: white;
            }
            
            .download-link {
                background-color: var(--secondary-color);
                color: white;
            }
            
            .back-link:hover, .download-link:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow);
            }
            
            .back-link i, .download-link i {
                margin-right: 8px;
            }
            
            @media (max-width: 768px) {
                .container {
                    padding: 20px 15px;
                }
                
                .header h1 {
                    font-size: 2rem;
                }
                
                .preview-container {
                    padding: 20px;
                    min-height: 300px;
                }
                
                .preview-video {
                    max-height: 400px;
                }
            }
            
            @media (max-width: 480px) {
                .header h1 {
                    font-size: 1.8rem;
                }
                
                .preview-actions {
                    flex-direction: column;
                }
                
                .back-link, .download-link {
                    width: 100%;
                    text-align: center;
                    justify-content: center;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-file"></i> 文件预览</h1>
            </div>
            
            <div class="content-card">
                <div class="card-body">
                    {$previewHtml}
                    {$fileInfoHtml}
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
}
