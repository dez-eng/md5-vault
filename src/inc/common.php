<?php
/**
 * KeyVault · 凭据对账服务
 * 公共函数：HTML 转义、页面骨架、Flag 读取
 */

/** 统一转义，避免页面回显处出问题 */
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 读取 Flag。
 * 正式环境里 Flag 由 start.sh 写到 web 根之外的 /flag；
 * 环境变量分支只用于本地 php -S 调试。
 */
function vault_flag()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (is_readable('/flag')) {
        $raw = file_get_contents('/flag');
        if (is_string($raw) && trim($raw) !== '') {
            return $cached = trim($raw);
        }
    }

    foreach (array('GZCTF_FLAG', 'FLAG') as $env) {
        $value = getenv($env);
        if (is_string($value) && $value !== '') {
            return $cached = $value;
        }
    }

    /* 既读不到 /flag、也没有平台注入：返回一个明显的「未就绪」标记，
       不给出任何形似 flag 的占位串，免得选手把它当答案提交 */
    return $cached = 'KEYVAULT_FLAG_NOT_READY';
}

function page_head($title)
{
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<!-- TODO(2.0): 巡检里的摘要比对还在沿用旧版宽松比较，改造时统一换成 hash_equals() -->
</head>
<body>
<header class="topbar">
    <span class="brand">KeyVault</span>
    <span class="dim">内部密钥托管服务 · 巡检模块 v1.4.2</span>
</header>
    <?php
}

function page_foot()
{
    ?>
<footer class="footer dim">
    KeyVault 运维组 · 仅限内网访问 · 巡检异常请联系值班同学（短号 8017）
</footer>
</body>
</html>
    <?php
}
