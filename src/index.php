<?php
require __DIR__ . '/inc/common.php';
page_head('KeyVault · 凭据对账');
?>
<main class="panel">
    <h1>凭据对账</h1>

    <p class="lead">
        KeyVault 里的每一把密钥都由两套独立系统各自保存一份凭据：本系统持有的
        <code>AccessKey</code>，与归档系统持有的 <code>SecretKey</code>。
    </p>
    <p class="lead">
        每日巡检会对这两条凭据做一次「摘要对账」：摘要一致，就说明两条记录指向同一把密钥，
        系统随即签发一份巡检回执。
    </p>

    <form class="form" method="post" action="/audit.php">
        <label>
            <span>AccessKey</span>
            <input type="text" name="access_key" autocomplete="off" spellcheck="false" placeholder="6~16 位字母或数字">
        </label>
        <label>
            <span>SecretKey</span>
            <input type="text" name="secret_key" autocomplete="off" spellcheck="false" placeholder="6~16 位字母或数字">
        </label>
        <button type="submit">发起对账</button>
    </form>

    <details class="rules">
        <summary>对账规则（内部版）</summary>
        <ol>
            <li>两条凭据都必须提交，且都是 <strong>6~16 位字母或数字</strong>（两套系统的生成规则一致）。</li>
            <li>两条凭据<strong>不能是同一串字符</strong>——巡检要求它们确实来自两套不同的系统。</li>
            <li>系统分别对两条凭据取 <strong>MD5</strong> 摘要，再比对两个摘要是否相等。</li>
        </ol>
        <p class="dim">
            说明：巡检脚本除了本表单，也允许直接以 URL 参数调用，例如
            <code>/audit.php?access_key=xxx&amp;secret_key=yyy</code>。
        </p>
    </details>

    <p class="dim">今天值班：老张 · 上次巡检：2024-11-08 03:00</p>
</main>
<?php
page_foot();
