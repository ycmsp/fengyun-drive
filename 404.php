<?php
/**
 * 404错误页面
 */

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <link rel="stylesheet" href="/assets/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            max-width: 600px;
            padding: 40px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 20px;
            line-height: 1;
        }
        .error-title {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        .error-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .error-icon {
            font-size: 80px;
            color: #3498db;
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #3498db;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        .home-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .home-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .error-container {
                padding: 30px 20px;
                margin: 20px;
            }
            .error-code {
                font-size: 80px;
            }
            .error-title {
                font-size: 24px;
            }
            .error-message {
                font-size: 16px;
            }
            .error-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-search"></i>
        </div>
        <div class="error-code">404</div>
        <h1 class="error-title">页面未找到</h1>
        <p class="error-message">
            抱歉，您访问的页面不存在或已被移除。<br>
            可能是链接错误或者页面已经移动。
        </p>
        <a href="<?php echo APP_URL; ?>" class="btn btn-primary">
            <i class="fas fa-home"></i> 返回首页
        </a>
        <a href="javascript:history.back()" class="home-link">
            <i class="fas fa-arrow-left"></i> 返回上一页
        </a>
    </div>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>