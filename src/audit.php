<?php
/**
 * KeyVault · 凭据对账接口
 *
 * 巡检逻辑：
 *   1. 两条凭据都要提交，格式为 6~16 位字母或数字
 *   2. 两条凭据不能是同一串字符
 *   3. 对两条凭据分别取 MD5 摘要，比对摘要是否相等
 */
require __DIR__ . '/inc/common.php';

/* 只认表单与 URL 参数（POST 优先），不用 $_REQUEST：
   $_REQUEST 会把 Cookie 也算成输入源（php.ini 缺省 variables_order=EGPCS 时） */
$params = $_POST + $_GET;

$access = isset($params['access_key']) ? $params['access_key'] : '';
$secret = isset($params['secret_key']) ? $params['secret_key'] : '';

$passed = false;
$reason = '';

if (!is_string($access) || !is_string($secret) || $access === '' || $secret === '') {
    $reason = '两条凭据都必须提交，且必须是非空字符串。';
} elseif (!preg_match('/^[A-Za-z0-9]{6,16}$/', $access) || !preg_match('/^[A-Za-z0-9]{6,16}$/', $secret)) {
    $reason = '凭据格式不合法：AccessKey / SecretKey 都必须是 6~16 位字母或数字。';
} elseif ($access === $secret) {
    $reason = '两条凭据不能是同一串字符：巡检要求它们确实来自两套不同的系统。';
} elseif (md5($access) == md5($secret)) {
    /* 旧版宽松比较：只要求两个摘要「相等」 */
    $passed = true;
} else {
    $reason = 'MD5 摘要不一致，对账未通过。';
}

page_head($passed ? 'KeyVault · 对账通过' : 'KeyVault · 对账未通过');
?>
<main class="panel">
    <h1>巡检回执</h1>

    <?php if ($passed): ?>
        <div class="result ok">
            <p class="result-title">对账通过 · 两条凭据指向同一把密钥</p>
            <dl class="kv">
                <dt>AccessKey</dt><dd><code><?= h($access) ?></code></dd>
                <dt>SecretKey</dt><dd><code><?= h($secret) ?></code></dd>
                <dt>MD5(AccessKey)</dt><dd><code><?= h(md5($access)) ?></code></dd>
                <dt>MD5(SecretKey)</dt><dd><code><?= h(md5($secret)) ?></code></dd>
                <dt>回执编号</dt><dd><code><?= h(bin2hex(random_bytes(8))) ?></code></dd>
            </dl>
            <p class="result-title">随回执放出的保险库口令：</p>
            <p class="flag"><?= h(vault_flag()) ?></p>
        </div>
    <?php else: ?>
        <div class="result fail">
            <p class="result-title">对账未通过</p>
            <p class="reason"><?= h($reason) ?></p>
            <dl class="kv">
                <dt>AccessKey</dt><dd><code><?= h(is_string($access) ? $access : '（非字符串）') ?></code></dd>
                <dt>SecretKey</dt><dd><code><?= h(is_string($secret) ? $secret : '（非字符串）') ?></code></dd>
            </dl>
        </div>
    <?php endif; ?>

    <p><a href="/">&larr; 返回凭据对账</a></p>
</main>
<?php
page_foot();
