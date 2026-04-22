/**
 * 网盘系统主应用脚本
 */

// 全局状态
const state = {
    currentFolder: 0,
    currentView: 'files',
    selectedFiles: [],
    breadcrumbs: [{ id: 0, name: '根目录' }],
    currentShareFile: null,
    currentMoveFile: null
};

// 上传任务状态管理
const uploadTasks = new Map();
// 存储正在进行的XHR请求
const uploadXHRs = new Map();

// API基础URL
const API_BASE = 'api';

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initFileManager();
    initModals();
    initOfflineDownload();
    loadFiles();
});

// 导航初始化
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            // 只有带有 data-view 属性的链接才阻止默认行为
            if (item.dataset.view) {
                e.preventDefault();
                const view = item.dataset.view;
                switchView(view);

                navItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
            }
            // 没有 data-view 属性的链接（如管理后台）会正常跳转
        });
    });
}

// 切换视图
function switchView(view) {
    state.currentView = view;
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.getElementById(`${view}-view`).classList.add('active');

    if (view === 'shares') {
        loadShares();
    }
}

// 文件管理器初始化
function initFileManager() {
    // 上传按钮
    document.getElementById('upload-btn').addEventListener('click', () => {
        document.getElementById('file-input').click();
    });

    // 文件选择
    document.getElementById('file-input').addEventListener('change', handleFileUpload);

    // 新建文件夹
    document.getElementById('mkdir-btn').addEventListener('click', () => {
        openModal('mkdir-modal');
    });

    // 搜索
    document.getElementById('search-btn').addEventListener('click', handleSearch);
    document.getElementById('search-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSearch();
    });

    // 确认创建文件夹
    document.getElementById('confirm-mkdir').addEventListener('click', createFolder);

    // 分享类型切换
    document.getElementById('share-type').addEventListener('change', (e) => {
        const passwordGroup = document.getElementById('password-group');
        passwordGroup.style.display = e.target.value === '2' ? 'block' : 'none';
    });

    // 确认创建分享
    document.getElementById('confirm-share').addEventListener('click', createShare);

    // 复制分享链接
    document.getElementById('copy-url').addEventListener('click', copyShareUrl);
    document.getElementById('copy-direct-url').addEventListener('click', copyDirectUrl);

    // 确认移动
    document.getElementById('confirm-move').addEventListener('click', confirmMove);

    // 个人资料表单
    document.getElementById('profile-form').addEventListener('submit', updateProfile);

    // 密码修改表单

    // 离线下载功能已在 DOMContentLoaded 中初始化
    document.getElementById('password-form').addEventListener('submit', changePassword);
}

// 弹窗初始化
function initModals() {
    // 关闭弹窗
    document.querySelectorAll('.modal-close, .modal-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            closeAllModals();
        });
    });

    // 点击弹窗外部关闭
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAllModals();
            }
        });
    });
}

// 打开弹窗
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

// 关闭所有弹窗
function closeAllModals() {
    // 停止所有媒体播放
    const previewContent = document.getElementById('preview-content');
    if (previewContent) {
        // 停止视频播放
        const videos = previewContent.querySelectorAll('video');
        videos.forEach(video => {
            video.pause();
            video.src = '';
        });
        
        // 停止音频播放
        const audios = previewContent.querySelectorAll('audio');
        audios.forEach(audio => {
            audio.pause();
            audio.src = '';
        });
        
        // 清空 iframe 内容，停止在线播放器
        const iframes = previewContent.querySelectorAll('iframe');
        iframes.forEach(iframe => {
            iframe.src = 'about:blank';
        });
    }
    
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });

    // 重置分享弹窗
    document.getElementById('share-result').style.display = 'none';
    document.getElementById('confirm-share').style.display = 'block';
    document.getElementById('share-type').value = '1';
    document.getElementById('share-password').value = '';
    document.getElementById('password-group').style.display = 'none';
}

// 加载文件列表
async function loadFiles(parentId = 0) {
    try {
        const response = await fetch(`${API_BASE}/files.php?action=list&parent_id=${parentId}`);
        const data = await response.json();

        if (data.success) {
            // 保存现有的上传任务行
            const uploadTaskRows = [];
            document.querySelectorAll('.upload-task').forEach(row => {
                uploadTaskRows.push(row.outerHTML);
            });

            renderFileList(data.data.files);
            
            // 重新添加上传任务行
            const tbody = document.getElementById('file-list-body');
            uploadTaskRows.forEach(html => {
                if (tbody.firstChild) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    tbody.insertBefore(tempDiv.firstChild, tbody.firstChild);
                } else {
                    tbody.innerHTML += html;
                }
            });

            renderBreadcrumbs(data.data.breadcrumbs);
            state.currentFolder = parentId;
            state.breadcrumbs = data.data.breadcrumbs;
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('加载文件失败', 'error');
    }
}

// 渲染文件列表
function renderFileList(files) {
    const tbody = document.getElementById('file-list-body');
    tbody.innerHTML = '';

    if (files.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    暂无文件
                </td>
            </tr>
        `;
        return;
    }

    files.forEach(file => {
        const tr = document.createElement('tr');
        const iconClass = file.is_folder ? 'fa-folder folder-icon' : getFileIconClass(file.file_extension);

        tr.innerHTML = `
            <td>
                <div class="file-name">
                    <i class="fas ${iconClass}"></i>
                    <span class="${file.is_folder ? 'folder-name' : ''}" data-id="${file.id}" data-folder="${file.is_folder}">
                        ${escapeHtml(file.filename)}
                    </span>
                </div>
            </td>
            <td>-</td>
            <td>${file.size_formatted}</td>
            <td>${file.hash || '-'}</td>
            <td>${formatDate(file.updated_at)}</td>
            <td>
                <div class="file-actions">
                    ${file.is_folder ? '' : `<button onclick="downloadFile(${file.id})" title="下载"><i class="fas fa-download"></i></button>`}
                    <button onclick="shareFile(${file.id})" title="分享"><i class="fas fa-share-alt"></i></button>
                    <button onclick="renameFile(${file.id}, '${escapeHtml(file.filename)}')" title="重命名"><i class="fas fa-edit"></i></button>
                    <button onclick="moveFile(${file.id})" title="移动"><i class="fas fa-arrows-alt"></i></button>
                    <button onclick="deleteFile(${file.id})" title="删除"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        `;

        tbody.appendChild(tr);
    });

    // 绑定文件夹和文件点击事件
    document.querySelectorAll('.file-name span').forEach(elem => {
        elem.addEventListener('click', () => {
            const fileId = parseInt(elem.dataset.id);
            const isFolder = elem.dataset.folder === '1';
            if (isFolder) {
                loadFiles(fileId);
            } else {
                previewFile(fileId);
            }
        });
    });
}

// 渲染面包屑
function renderBreadcrumbs(breadcrumbs) {
    const container = document.getElementById('breadcrumb');
    container.innerHTML = breadcrumbs.map((crumb, index) => {
        const isLast = index === breadcrumbs.length - 1;
        return isLast
            ? `<span class="breadcrumb-item active">${escapeHtml(crumb.name)}</span>`
            : `<span class="breadcrumb-item" data-id="${crumb.id}">${escapeHtml(crumb.name)}</span>`;
    }).join(' <span style="color: #ccc;">/</span> ');

    // 绑定点击事件
    document.querySelectorAll('.breadcrumb-item[data-id]').forEach(item => {
        item.addEventListener('click', () => {
            loadFiles(parseInt(item.dataset.id));
        });
    });
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

// 获取系统设置
async function getSettings() {
    try {
        const response = await fetch(`${API_BASE}/files.php?action=get_settings`);
        const data = await response.json();
        if (data.success) {
            return data.data;
        }
    } catch (error) {
        console.error('获取设置失败:', error);
    }
    // 默认设置
    return {
        chunk_size: 10, // 默认10MB
        parallel_uploads: 3 // 默认3个并行
    };
}

// 处理文件上传
async function handleFileUpload(e) {
    const files = e.target.files;
    if (files.length === 0) return;

    // 获取系统设置
    const settings = await getSettings();
    const chunkSize = settings.chunk_size * 1024 * 1024; // 转换为字节
    const parallelUploads = settings.parallel_uploads;

    for (const file of files) {
        // 生成唯一任务ID
        const taskId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        // 添加到上传任务列表
        uploadTasks.set(taskId, {
            file: file,
            progress: 0,
            status: 'uploading',
            uploadedChunks: 0,
            totalChunks: Math.ceil(file.size / chunkSize)
        });
        
        // 渲染上传任务行
        renderUploadTask(taskId, file);

        try {
            await uploadFileInChunks(file, taskId, chunkSize, parallelUploads);
        } catch (error) {
            console.error('上传失败:', error);
            updateUploadStatus(taskId, 'error', '上传失败：未知错误');
        }
    }

    // 清空input，允许重复上传相同文件
    e.target.value = '';

    // 刷新文件列表
    loadFiles(state.currentFolder);

    // 刷新存储空间显示
    updateStorageInfo();
}

// 分块上传文件
async function uploadFileInChunks(file, taskId, chunkSize, parallelUploads) {
    // 检查任务是否已被取消
    let task = uploadTasks.get(taskId);
    if (task && task.status === 'cancelled') {
        console.log('上传任务已被取消:', taskId);
        return;
    }

    const totalChunks = Math.ceil(file.size / chunkSize);
    if (task) {
        task.totalChunks = totalChunks;
        task.uploadedChunks = 0;
        task.uploadedBytes = 0;
        task.startTime = Date.now();
        task.lastUpdateTime = Date.now();
        task.lastUploadedBytes = 0;
        uploadTasks.set(taskId, task);
    }

    // 生成唯一的上传会话ID
    const sessionId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    // 开始计算上传速度
    let previousUploadedBytes = 0;
    let previousTime = Date.now();
    
    const speedUpdateInterval = setInterval(() => {
        const task = uploadTasks.get(taskId);
        if (task && task.status === 'uploading') {
            const now = Date.now();
            const elapsedTime = (now - previousTime) / 1000; // 秒
            if (elapsedTime > 0) {
                const uploadedBytes = task.uploadedBytes - previousUploadedBytes;
                const speed = uploadedBytes / elapsedTime; // 字节/秒
                task.speed = speed;
                previousUploadedBytes = task.uploadedBytes;
                previousTime = now;
                uploadTasks.set(taskId, task);
                
                // 更新上传速度显示
                const tr = document.getElementById(`upload-task-${taskId}`);
                if (tr) {
                    const statusElement = tr.querySelector('.upload-status');
                    if (statusElement) {
                        statusElement.textContent = formatFileSize(speed) + '/s';
                    }
                }
            }
        }
    }, 1000); // 每秒更新一次

    // 分块上传
    const chunks = [];
    for (let i = 0; i < totalChunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        chunks.push({ index: i, start, end });
    }

    try {
        // 并行上传
        const results = await parallelize(chunks, async (chunk) => {
            return await uploadChunk(file, chunk, taskId, sessionId, chunkSize);
        }, parallelUploads);

        // 检查任务是否已被取消
        task = uploadTasks.get(taskId);
        if (task && task.status === 'cancelled') {
            console.log('上传任务已被取消，跳过合并块:', taskId);
            // 清理XHR请求
            uploadXHRs.delete(taskId);
            return;
        }

        // 检查是否所有块都上传成功
        const allSuccess = results.every(result => result.success);
        if (!allSuccess) {
            throw new Error('部分块上传失败');
        }

        // 合并块
        await mergeChunks(sessionId, file.name, file.size, taskId);
    } finally {
        // 清除速度更新定时器
        clearInterval(speedUpdateInterval);
        
        // 清理XHR请求
        uploadXHRs.delete(taskId);
    }
}

// 并行执行函数
async function parallelize(items, fn, limit) {
    const results = [];
    const running = [];

    for (const item of items) {
        if (running.length >= limit) {
            await Promise.race(running);
        }

        const promise = fn(item).then(result => {
            running.splice(running.indexOf(promise), 1);
            return result;
        });

        running.push(promise);
        results.push(promise);
    }

    return await Promise.all(results);
}

// 上传单个块
async function uploadChunk(file, chunk, taskId, sessionId, chunkSize) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        
        // 检查任务是否已被取消
        const task = uploadTasks.get(taskId);
        if (task && task.status === 'cancelled') {
            resolve({ success: false, message: '上传已取消' });
            return;
        }
        
        // 切割文件块
        const blob = file.slice(chunk.start, chunk.end);
        
        formData.append('chunk', blob);
        formData.append('index', chunk.index);
        formData.append('session_id', sessionId);
        formData.append('file_name', file.name);
        formData.append('file_size', file.size);
        formData.append('parent_id', state.currentFolder);
        
        // 存储XHR请求
        if (!uploadXHRs.has(taskId)) {
            uploadXHRs.set(taskId, []);
        }
        uploadXHRs.get(taskId).push(xhr);

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const task = uploadTasks.get(taskId);
                if (task) {
                    const chunkProgress = (event.loaded / event.total) * 100;
                    const overallProgress = ((chunk.index * 100) + chunkProgress) / task.totalChunks;
                    updateUploadProgress(taskId, overallProgress);
                    
                    // 更新已上传字节数
                    task.uploadedBytes = (chunk.index * chunkSize) + event.loaded;
                    uploadTasks.set(taskId, task);
                }
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        const task = uploadTasks.get(taskId);
                        if (task) {
                            task.uploadedChunks += 1;
                            uploadTasks.set(taskId, task);
                        }
                        resolve({ success: true });
                    } else {
                        resolve({ success: false, message: data.message });
                    }
                } catch (error) {
                    resolve({ success: false, message: '无效的响应' });
                }
            } else {
                resolve({ success: false, message: `HTTP错误 ${xhr.status}` });
            }
        });

        xhr.addEventListener('error', () => {
            resolve({ success: false, message: '网络错误' });
        });

        xhr.open('POST', `${API_BASE}/files.php?action=upload_chunk`);
        xhr.timeout = 3600000; // 1小时超时
        xhr.send(formData);
    });
}

// 合并块
async function mergeChunks(sessionId, fileName, fileSize, taskId) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        
        formData.append('session_id', sessionId);
        formData.append('file_name', fileName);
        formData.append('file_size', fileSize);
        formData.append('parent_id', state.currentFolder);

        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        updateUploadStatus(taskId, 'success', '上传成功');
                        
                        // 更新MD5显示
                        const tr = document.getElementById(`upload-task-${taskId}`);
                        if (tr) {
                            const md5Cell = tr.querySelector('td:nth-child(4)');
                            if (md5Cell) {
                                md5Cell.textContent = data.hash || '-';
                            }
                        }
                        
                        resolve();
                    } else {
                        updateUploadStatus(taskId, 'error', data.message);
                        reject(new Error(data.message));
                    }
                } catch (error) {
                    updateUploadStatus(taskId, 'error', '合并失败：无效的响应');
                    reject(error);
                }
            } else {
                updateUploadStatus(taskId, 'error', `合并失败：HTTP错误 ${xhr.status}`);
                reject(new Error(`HTTP error! status: ${xhr.status}`));
            }
        });

        xhr.addEventListener('error', () => {
            updateUploadStatus(taskId, 'error', '合并失败：网络错误');
            reject(new Error('Network error'));
        });

        xhr.open('POST', `${API_BASE}/files.php?action=merge_chunks`);
        xhr.timeout = 3600000; // 1小时超时
        xhr.send(formData);
    });
}

// 渲染上传任务行
function renderUploadTask(taskId, file) {
    const tbody = document.getElementById('file-list-body');
    
    // 创建上传任务行
    const tr = document.createElement('tr');
    tr.id = `upload-task-${taskId}`;
    tr.className = 'upload-task';
    
    const iconClass = getFileIconClass(file.name.split('.').pop());
    
    tr.innerHTML = `
        <td>
            <div class="file-name">
                <i class="fas ${iconClass}"></i>
                <span>${escapeHtml(file.name)}</span>
            </div>
        </td>
        <td>
            <div class="upload-progress-container">
                <div class="upload-progress-bar" style="width: 0%"></div>
                <span class="upload-progress-text">0%</span>
                <span class="upload-status">上传中...</span>
            </div>
        </td>
        <td>${formatFileSize(file.size)}</td>
        <td>-</td>
        <td>-</td>
        <td>
            <button class="btn btn-danger btn-sm cancel-upload" data-task-id="${taskId}">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    
    // 插入到文件列表顶部
    if (tbody.firstChild) {
        tbody.insertBefore(tr, tbody.firstChild);
    } else {
        tbody.appendChild(tr);
    }
}

// 更新上传进度
function updateUploadProgress(taskId, progress) {
    const task = uploadTasks.get(taskId);
    if (task) {
        task.progress = progress;
        uploadTasks.set(taskId, task);
        
        const tr = document.getElementById(`upload-task-${taskId}`);
        if (tr) {
            const progressBar = tr.querySelector('.upload-progress-bar');
            const progressText = tr.querySelector('.upload-progress-text');
            
            if (progressBar && progressText) {
                // 使用更兼容的方式设置宽度
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
                
                // 强制浏览器重绘
                progressBar.offsetHeight;
            }
        }
    }
}

// 更新上传状态
function updateUploadStatus(taskId, status, message) {
    const task = uploadTasks.get(taskId);
    if (task) {
        task.status = status;
        uploadTasks.set(taskId, task);
        
        const tr = document.getElementById(`upload-task-${taskId}`);
        if (tr) {
            const statusElement = tr.querySelector('.upload-status');
            
            if (statusElement) {
                statusElement.textContent = message;
                statusElement.className = `upload-status ${status}`;
            }
        }
    }
}

// 创建文件夹
async function createFolder() {
    const name = document.getElementById('folder-name').value.trim();
    if (!name) {
        showToast('请输入文件夹名称', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/files.php?action=mkdir`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, parent_id: state.currentFolder })
        });
        const data = await response.json();

        if (data.success) {
            showToast('文件夹创建成功', 'success');
            closeAllModals();
            document.getElementById('folder-name').value = '';
            loadFiles(state.currentFolder);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('创建失败', 'error');
    }
}

// 下载文件
function downloadFile(fileId) {
    window.location.href = `${API_BASE}/files.php?action=download&file_id=${fileId}`;
}

// 分享文件
function shareFile(fileId) {
    state.currentShareFile = fileId;
    openModal('share-modal');
}

// 绑定取消上传按钮事件
document.addEventListener('click', function(e) {
    if (e.target.closest('.cancel-upload')) {
        const button = e.target.closest('.cancel-upload');
        const taskId = button.dataset.taskId;
        cancelUpload(taskId);
    }
});

// 取消上传任务
function cancelUpload(taskId) {
    const task = uploadTasks.get(taskId);
    if (task) {
        // 中止正在进行的XHR请求
        if (uploadXHRs.has(taskId)) {
            const xhrs = uploadXHRs.get(taskId);
            xhrs.forEach(xhr => {
                if (xhr.readyState < 4) {
                    xhr.abort();
                }
            });
            uploadXHRs.delete(taskId);
        }
        
        // 从上传任务列表中移除
        uploadTasks.delete(taskId);
        
        // 从UI中移除任务行
        const tr = document.getElementById(`upload-task-${taskId}`);
        if (tr) {
            tr.remove();
        }
        
        // 从localStorage中移除
        saveUploadTasks();
        
        console.log('上传任务已取消并移除:', taskId);
    }
}

// 创建分享
async function createShare() {
    const shareType = document.getElementById('share-type').value;
    const password = document.getElementById('share-password').value;
    const expireDays = document.getElementById('share-expire').value;

    if (shareType === '2' && !password) {
        showToast('请输入分享密码', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/share.php?action=create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file_id: state.currentShareFile,
                share_type: parseInt(shareType),
                password: password,
                expire_days: parseInt(expireDays)
            })
        });
        const data = await response.json();

        if (data.success) {
            document.getElementById('share-url').value = data.data.share_url;
            document.getElementById('share-code-display').value = data.data.share_code;
            document.getElementById('share-result').style.display = 'block';
            document.getElementById('confirm-share').style.display = 'none';
            
            // 生成直链
            try {
                if (data.data.direct_link) {
                    document.getElementById('direct-url').value = data.data.direct_link;
                } else {
                    const shareCode = data.data.share_code;
                    fetch(`${API_BASE}/share.php?action=generateDirectLink&code=${shareCode}`)
                        .then(response => response.json())
                        .then(directData => {
                            if (directData.success) {
                                document.getElementById('direct-url').value = directData.data.direct_link;
                            } else {
                                console.error('生成直链失败:', directData.message);
                                document.getElementById('direct-url').value = '生成失败';
                            }
                        })
                        .catch(err => {
                            console.error('生成直链错误:', err);
                            document.getElementById('direct-url').value = '生成失败';
                        });
                }
            } catch (err) {
                console.error('生成直链错误:', err);
                document.getElementById('direct-url').value = '生成失败';
            }
            
            showToast('分享创建成功', 'success');
        } else {
            showToast('分享失败: ' + data.message, 'error');
        }
    } catch (error) {
        showToast('创建分享失败: ' + error.message, 'error');
        console.error('分享错误:', error);
    }
}

// 复制分享链接
function copyShareUrl() {
    const input = document.getElementById('share-url');
    input.select();
    document.execCommand('copy');
    showToast('链接已复制', 'success');
}

// 复制直链
function copyDirectUrl() {
    const input = document.getElementById('direct-url');
    input.select();
    document.execCommand('copy');
    showToast('直链已复制', 'success');
}

// 重命名文件
async function renameFile(fileId, currentName) {
    const newName = prompt('请输入新名称:', currentName);
    if (!newName || newName === currentName) return;

    try {
        const response = await fetch(`${API_BASE}/files.php?action=rename`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: fileId, new_name: newName })
        });
        const data = await response.json();

        if (data.success) {
            showToast('重命名成功', 'success');
            loadFiles(state.currentFolder);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('重命名失败', 'error');
    }
}

// 移动文件
async function moveFile(fileId) {
    state.currentMoveFile = fileId;

    // 加载文件夹树
    try {
        const response = await fetch(`${API_BASE}/files.php?action=list&parent_id=0`);
        const data = await response.json();

        if (data.success) {
            renderFolderTree(data.data.files.filter(f => f.is_folder && f.id !== fileId));
            openModal('move-modal');
        }
    } catch (error) {
        showToast('加载文件夹失败', 'error');
    }
}

// 渲染文件夹树
function renderFolderTree(folders) {
    const container = document.getElementById('folder-tree');
    container.innerHTML = `
        <div class="folder-tree-item selected" data-id="0">
            <i class="fas fa-folder"></i>
            <span>根目录</span>
        </div>
        ${folders.map(folder => `
            <div class="folder-tree-item" data-id="${folder.id}">
                <i class="fas fa-folder"></i>
                <span>${escapeHtml(folder.filename)}</span>
            </div>
        `).join('')}
    `;

    // 绑定选择事件
    document.querySelectorAll('.folder-tree-item').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.folder-tree-item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
        });
    });
}

// 确认移动
async function confirmMove() {
    const selected = document.querySelector('.folder-tree-item.selected');
    if (!selected) return;

    const targetId = parseInt(selected.dataset.id);

    try {
        const response = await fetch(`${API_BASE}/files.php?action=move`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: state.currentMoveFile, target_parent_id: targetId })
        });
        const data = await response.json();

        if (data.success) {
            showToast('移动成功', 'success');
            closeAllModals();
            loadFiles(state.currentFolder);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('移动失败', 'error');
    }
}

// 删除文件
async function deleteFile(fileId) {
    if (!confirm('确定要删除这个文件吗？')) return;

    try {
        const response = await fetch(`${API_BASE}/files.php?action=delete`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: fileId })
        });
        const data = await response.json();

        if (data.success) {
            showToast('删除成功', 'success');
            loadFiles(state.currentFolder);
            updateStorageInfo();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('删除失败', 'error');
    }
}

// 搜索文件
async function handleSearch() {
    const keyword = document.getElementById('search-input').value.trim();
    if (!keyword) {
        loadFiles(state.currentFolder);
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/files.php?action=search&keyword=${encodeURIComponent(keyword)}`);
        const data = await response.json();

        if (data.success) {
            renderFileList(data.data.files);
            document.getElementById('breadcrumb').innerHTML = '<span class="breadcrumb-item active">搜索结果: ' + escapeHtml(keyword) + '</span>';
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('搜索失败', 'error');
    }
}

// 加载分享列表
async function loadShares() {
    try {
        const response = await fetch(`${API_BASE}/share.php?action=list`);
        const data = await response.json();

        if (data.success) {
            renderShareList(data.data.shares);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('加载分享列表失败', 'error');
    }
}

// 渲染分享列表
function renderShareList(shares) {
    const tbody = document.getElementById('share-list-body');
    tbody.innerHTML = '';

    if (shares.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                    暂无分享
                </td>
            </tr>
        `;
        return;
    }

    shares.forEach(share => {
        const tr = document.createElement('tr');
        const isExpired = share.is_expired;

        tr.innerHTML = `
            <td>${escapeHtml(share.filename)}</td>
            <td>${share.is_folder ? '文件夹' : '文件'}</td>
            <td>
                <code>${share.share_code}</code>
                ${share.share_type == 2 ? '<i class="fas fa-lock" title="需要密码"></i>' : ''}
            </td>
            <td>${share.access_count} / ${share.download_count}</td>
            <td>
                ${share.expire_at ? formatDate(share.expire_at) : '永久'}
                ${isExpired ? ' <span style="color: red;">(已过期)</span>' : ''}
            </td>
            <td>
                <div class="file-actions">
                    <button onclick="copyShareLink('${share.share_code}')" title="复制链接">
                        <i class="fas fa-link"></i>
                    </button>
                    <button onclick="cancelShare(${share.id})" title="取消分享">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </td>
        `;

        tbody.appendChild(tr);
    });
}

// 复制分享链接
function copyShareLink(code) {
    const url = `${window.location.origin}/share.php?code=${code}`;
    navigator.clipboard.writeText(url).then(() => {
        showToast('链接已复制', 'success');
    });
}

// 取消分享
async function cancelShare(shareId) {
    if (!confirm('确定要取消这个分享吗？')) return;

    try {
        const response = await fetch(`${API_BASE}/share.php?action=cancel`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ share_id: shareId })
        });
        const data = await response.json();

        if (data.success) {
            showToast('分享已取消', 'success');
            loadShares();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('取消分享失败', 'error');
    }
}

// 更新个人资料
async function updateProfile(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    // 确保 checkbox 未勾选时也发送值
    data.directory_browsing = formData.has('directory_browsing') ? '1' : '0';
    
    console.log('Form data:', data);

    try {
        const response = await fetch(`${API_BASE}/auth.php?action=profile`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        console.log('Response:', result);

        if (result.success) {
            showToast('资料更新成功', 'success');
            // 刷新页面以显示最新设置
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('更新失败', 'error');
    }
}

// 修改密码
async function changePassword(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    if (data.new_password !== data.confirm_password) {
        showToast('两次输入的密码不一致', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/auth.php?action=password`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                old_password: data.old_password,
                new_password: data.new_password
            })
        });
        const result = await response.json();

        if (result.success) {
            showToast('密码修改成功', 'success');
            e.target.reset();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('修改失败', 'error');
    }
}

// 更新存储空间信息
async function updateStorageInfo() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=profile`);
        const data = await response.json();

        if (data.success) {
            const user = data.data;
            const percent = user.storage_limit > 0 ? (user.storage_used / user.storage_limit * 100).toFixed(2) : 0;

            document.getElementById('storage-text').textContent = `${formatFileSize(user.storage_used)} / ${formatFileSize(user.storage_limit)}`;
            document.querySelector('.storage-progress').style.width = `${percent}%`;
            document.querySelector('.storage-percent').textContent = `${percent}%`;
        }
    } catch (error) {
        console.error('更新存储空间失败', error);
    }
}

// 工具函数：显示提示
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// 工具函数：格式化日期
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString('zh-CN');
}

// 工具函数：格式化文件大小
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

// 转义HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 初始化离线下载功能
function initOfflineDownload() {
    // 加载离线下载任务列表
    document.querySelector('[data-view="offline"]').addEventListener('click', function(e) {
        e.preventDefault();
        switchView('offline');
        loadOfflineTasks();
        // 开始自动刷新
        startOfflineTasksRefresh();
    });

    // 监听视图切换，当离开离线下载视图时停止刷新
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        if (item.dataset.view && item.dataset.view !== 'offline') {
            item.addEventListener('click', function() {
                // 停止自动刷新
                stopOfflineTasksRefresh();
            });
        }
    });

    // 添加离线下载任务按钮
    document.getElementById('add-offline-task').addEventListener('click', function() {
        openOfflineTaskModal();
    });

    // 确认添加离线下载任务
    document.getElementById('confirm-offline-task').addEventListener('click', function() {
        addOfflineTask();
    });
}

// 刷新定时器
let offlineTasksRefreshInterval = null;

// 加载离线下载任务列表
async function loadOfflineTasks() {
    try {
        const response = await fetch(`${API_BASE}/offline.php?action=list`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            const taskListBody = document.getElementById('offline-task-list-body');
            taskListBody.innerHTML = '';

            if (data.data.tasks.length === 0) {
                taskListBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">暂无离线下载任务</td></tr>';
                return;
            }

            data.data.tasks.forEach(task => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="col-name">${task.task_name}</td>
                    <td class="col-url"><a href="${task.download_url}" target="_blank">${task.download_url}</a></td>
                    <td class="col-status">
                        <span class="status-badge ${getStatusClass(task.status)}">${task.status_text}</span>
                    </td>
                    <td class="col-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${task.progress}%"></div>
                        </div>
                        <span class="progress-text">${task.progress}%</span>
                    </td>
                    <td class="col-size">${task.size_formatted}</td>
                    <td class="col-date">${task.created_at}</td>
                    <td class="col-actions">
                        ${task.status < 2 ? `<button class="btn btn-sm btn-danger cancel-task" data-id="${task.id}">取消</button>` : ''}
                        <button class="btn btn-sm btn-danger delete-task" data-id="${task.id}">删除</button>
                    </td>
                `;
                taskListBody.appendChild(row);
            });

            // 添加取消和删除事件监听器
            document.querySelectorAll('.cancel-task').forEach(btn => {
                btn.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-id');
                    cancelOfflineTask(taskId);
                });
            });

            document.querySelectorAll('.delete-task').forEach(btn => {
                btn.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-id');
                    deleteOfflineTask(taskId);
                });
            });
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('加载离线下载任务失败', 'error');
    }
}

// 开始自动刷新离线下载任务
function startOfflineTasksRefresh() {
    // 清除之前的定时器
    if (offlineTasksRefreshInterval) {
        clearInterval(offlineTasksRefreshInterval);
    }
    
    // 设置新的定时器，每5秒刷新一次，减少请求频率
    offlineTasksRefreshInterval = setInterval(() => {
        loadOfflineTasks();
    }, 5000);
}

// 停止自动刷新离线下载任务
function stopOfflineTasksRefresh() {
    if (offlineTasksRefreshInterval) {
        clearInterval(offlineTasksRefreshInterval);
        offlineTasksRefreshInterval = null;
    }
}

// 获取状态样式类
function getStatusClass(status) {
    switch (status) {
        case 0:
            return 'status-waiting';
        case 1:
            return 'status-downloading';
        case 2:
            return 'status-completed';
        case 3:
            return 'status-failed';
        default:
            return '';
    }
}

// 打开离线下载任务弹窗
function openOfflineTaskModal() {
    // 加载文件夹列表
    loadFoldersForOffline();
    
    // 打开弹窗
    openModal('offline-task-modal');
    
    // 等待弹窗完全打开，确保DOM元素存在
    setTimeout(function() {
        // 重置表单
        const urlInput = document.getElementById('offline-url');
        const taskNameInput = document.getElementById('offline-task-name');
        const savePathSelect = document.getElementById('offline-save-path');
        
        if (urlInput && taskNameInput && savePathSelect) {
            console.log('DOM元素已找到');
            
            urlInput.value = '';
            taskNameInput.value = '';
            savePathSelect.value = '0';
            
            // 移除旧的事件监听器，避免重复添加
            urlInput.removeEventListener('input', handleUrlInput);
            // 添加新的事件监听器，自动从下载链接中提取文件名
            urlInput.addEventListener('input', handleUrlInput);
            console.log('事件监听器已添加');
        } else {
            console.error('DOM元素未找到');
        }
    }, 100);
}

// 处理URL输入事件
function handleUrlInput() {
    const url = this.value;
    const taskNameInput = document.getElementById('offline-task-name');
    console.log('URL输入变化:', url);
    if (url) {
        // 从URL中提取文件名
        const filename = extractFilenameFromUrl(url);
        console.log('提取的文件名:', filename);
        if (filename) {
            taskNameInput.value = filename;
            console.log('任务名称已更新为:', filename);
        }
    } else {
        taskNameInput.value = '';
        console.log('URL为空，任务名称已清空');
    }
}

// 从URL中提取文件名
function extractFilenameFromUrl(url) {
    try {
        // 解析URL
        const parsedUrl = new URL(url);
        // 获取路径部分
        const path = parsedUrl.pathname;
        // 提取最后一个部分作为文件名
        const filename = path.split('/').pop();
        // 解码URL编码的字符
        return decodeURIComponent(filename) || '';
    } catch (error) {
        // 如果URL解析失败，尝试直接从字符串中提取
        const parts = url.split('/');
        const filename = parts.pop();
        return decodeURIComponent(filename) || '';
    }
}

// 加载文件夹列表用于离线下载
async function loadFoldersForOffline() {
    try {
        console.log('开始加载文件夹列表...');
        const select = document.getElementById('offline-save-path');
        // 清空下拉列表
        select.innerHTML = '';
        
        // 添加根目录选项
        const rootOption = document.createElement('option');
        rootOption.value = '0';
        rootOption.textContent = '我的根目录';
        select.appendChild(rootOption);

        // 递归加载所有文件夹
        await loadFoldersRecursive(0, '');
        console.log('文件夹列表加载完成');
    } catch (error) {
        console.error('加载文件夹列表失败:', error);
    }
}

// 递归加载文件夹
async function loadFoldersRecursive(parentId, prefix) {
    try {
        const response = await fetch(`${API_BASE}/files.php?action=list&parent_id=${parentId}`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.data && data.data.files) {
            const select = document.getElementById('offline-save-path');
            
            // 添加当前目录下的文件夹
            data.data.files.forEach(file => {
                if (file.is_folder) {
                    // 检查该文件夹是否已经在下拉列表中
                    const existingOption = select.querySelector(`option[value="${file.id}"]`);
                    if (!existingOption) {
                        const option = document.createElement('option');
                        option.value = file.id;
                        option.textContent = prefix + file.filename;
                        select.appendChild(option);
                        
                        // 递归加载子文件夹
                        loadFoldersRecursive(file.id, prefix + file.filename + '/');
                    }
                }
            });
        } else {
            console.warn('API 返回数据格式不正确:', data);
        }
    } catch (error) {
        console.error('加载文件夹失败:', error);
    }
}

// 添加离线下载任务
async function addOfflineTask() {
    const url = document.getElementById('offline-url').value;
    const taskName = document.getElementById('offline-task-name').value;
    const savePath = document.getElementById('offline-save-path').value;

    if (!url) {
        showToast('请输入下载链接', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/offline.php?action=add`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                url: url,
                task_name: taskName,
                save_path: savePath
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('任务添加成功', 'success');
            closeAllModals();
            loadOfflineTasks();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('任务添加失败', 'error');
    }
}

// 取消离线下载任务
async function cancelOfflineTask(taskId) {
    if (!confirm('确定要取消这个下载任务吗？')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/offline.php?action=cancel`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('任务已取消', 'success');
            loadOfflineTasks();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('取消任务失败', 'error');
    }
}

// 删除离线下载任务
async function deleteOfflineTask(taskId) {
    if (!confirm('确定要删除这个下载任务吗？')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/offline.php?action=delete`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('任务已删除', 'success');
            loadOfflineTasks();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('删除任务失败', 'error');
    }
}

// 下载文件
/*
async function downloadFile(fileId) {
    try {
        const response = await fetch(`${API_BASE}/files.php?action=download&id=${fileId}`);
        const data = await response.json();

        if (data.success) {
            // 创建下载链接
            const link = document.createElement('a');
            link.href = data.data.url;
            link.download = data.data.filename;
            link.click();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('下载失败', 'error');
    }
}
*/

// 预览文件
async function previewFile(fileId) {
    try {
        const response = await fetch(`${API_BASE}/files.php?action=info&id=${fileId}`);
        const data = await response.json();

        if (data.success) {
            const file = data.data;
            const ext = file.file_extension?.toLowerCase();
            
            // 检查是否支持预览
            if (isPreviewable(ext)) {
                openPreviewModal(file);
            } else {
                // 不支持预览的文件直接下载
                downloadFile(fileId);
            }
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('预览失败', 'error');
    }
}

// 检查文件是否可预览
function isPreviewable(ext) {
    const previewableExts = [
        // 图片
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
        // 文本
        'txt', 'md', 'html', 'css', 'js', 'php', 'py',
        // 文档
        'pdf',
        // 视频
        'mp4', 'webm', 'avi', 'mkv', 'mov',
        // 音频
        'mp3', 'wav', 'ogg', 'aac', 'flac'
    ];
    return previewableExts.includes(ext);
}

// 打开预览弹窗
function openPreviewModal(file) {
    const modal = document.getElementById('preview-modal');
    const previewContent = document.getElementById('preview-content');
    const previewTitle = document.getElementById('preview-title');
    
    previewTitle.textContent = file.filename;
    
    const ext = file.file_extension?.toLowerCase();
    const url = `${API_BASE}/files.php?action=direct&id=${file.id}`;
    
    // 根据文件类型生成预览内容
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) {
        // 图片预览
        previewContent.innerHTML = `
            <div class="preview-image">
                <img src="${url}" alt="${file.filename}">
            </div>
        `;
    } else if (['mp4', 'webm', 'avi', 'mkv', 'mov'].includes(ext)) {
        // 检查是否为本地环境
        const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        
        if (isLocal) {
            // 本地环境使用默认的 H5 播放器
            previewContent.innerHTML = `
                <div class="preview-video">
                    <video controls>
                        <source src="${url}" type="video/${ext}">
                        您的浏览器不支持视频播放
                    </video>
                </div>
            `;
        } else {
            // 非本地环境使用 v.jiasu7.top 播放器
            const playerUrl = `https://v.jiasu7.top/jx.php?url=${encodeURIComponent(url)}`;
            previewContent.innerHTML = `
                <div class="preview-video">
                    <iframe src="${playerUrl}" frameborder="0" width="100%" height="500px"></iframe>
                </div>
            `;
        }
    } else if (['mp3', 'wav', 'ogg', 'aac', 'flac'].includes(ext)) {
        // 检查是否为本地环境
        const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        
        if (isLocal) {
            // 本地环境使用默认的 H5 播放器
            previewContent.innerHTML = `
                <div class="preview-audio">
                    <audio controls>
                        <source src="${url}" type="audio/${ext}">
                        您的浏览器不支持音频播放
                    </audio>
                </div>
            `;
        } else {
            // 非本地环境使用 v.jiasu7.top 播放器
            const playerUrl = `https://v.jiasu7.top/jx.php?url=${encodeURIComponent(url)}`;
            previewContent.innerHTML = `
                <div class="preview-audio">
                    <iframe src="${playerUrl}" frameborder="0" width="100%" height="100px"></iframe>
                </div>
            `;
        }
    } else if (ext === 'pdf') {
        // PDF预览
        previewContent.innerHTML = `
            <div class="preview-pdf">
                <iframe src="${url}" frameborder="0"></iframe>
            </div>
        `;
    } else if (['txt', 'md', 'html', 'css', 'js', 'php', 'py'].includes(ext)) {
        // 文本文件预览
        fetch(url)
            .then(response => response.text())
            .then(text => {
                previewContent.innerHTML = `
                    <div class="preview-text">
                        <pre>${escapeHtml(text)}</pre>
                    </div>
                `;
            })
            .catch(error => {
                previewContent.innerHTML = '<div class="preview-error">预览失败</div>';
            });
        modal.classList.add('active');
        return;
    }
    
    modal.classList.add('active');
}
