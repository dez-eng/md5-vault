# KeyVault · 凭据对账（md5 绕过 · 难度 1）

一道 GZCTF **动态容器题**。页面是一段可信的运维巡检剧情，考点只有一个：**PHP `md5()` 的宽松比较（`==`）**，
用经典的 **0e 科学计数法** 提交两条不同却「摘要相等」的凭据即可拿到回执里的 Flag。

| 项目 | 说明 |
| --- | --- |
| 题目类型 | Web · 动态容器（Docker） |
| 难度 | ★☆☆☆☆ 1/5 |
| 考点 | `md5($a) == md5($b)` 宽松比较：`0e` 开头的摘要被当成数值 `0` 比较 |
| 入口 | 容器 80 端口：`/`（对账表单）、`/audit.php`（对账接口，支持 GET/POST） |
| Flag | 平台动态注入（`GZCTF_FLAG`），落到 web 根之外的 `/flag` |
| 预计耗时 | 新手 5~10 分钟 |

---

## 一、题面（可直接发布）

> KeyVault 是运维组自研的内部密钥托管服务。每一把密钥都由两套系统各存一份凭据：本系统持有的
> `AccessKey`，和归档系统持有的 `SecretKey`。
>
> 每日巡检会比对这两条凭据的摘要，摘要一致就说明两条记录指向同一把密钥，系统随即签发巡检回执。
> 组里有个不成文的规矩：签发的唯一依据就是「两条凭据的 **MD5 摘要相等**」，从来没人核对过这两条
> 凭据到底是不是同一份东西。
>
> 让巡检通过一次，回执里会带出保险库口令。

**Flag 格式**：由竞赛平台配置的动态模板（例如 `HuSec2026{[GUID]}`）；本地自测时用 `flag{...}`。

**建议给出的提示（难度 1 可直接给）**：

- 页面上写着「两条凭据不能是同一串字符」——想想哪些输入在 PHP 里「看起来相等」但字面不同。
- PHP 里两个以 `0e` 开头、后面全是数字的字符串，`==` 比较时会被当成浮点数 `0`。
- 常见的 `md5()` 宽松比较绕过 payload：`240610708` 与 `QNKCDZO`。

---

## 二、考点与设计说明

校验逻辑（`src/audit.php`）：

```php
if (!is_string($access) || !is_string($secret) || $access === '' || $secret === '') {
    $reason = '两条凭据都必须提交，且必须是非空字符串。';
} elseif (!preg_match('/^[A-Za-z0-9]{6,16}$/', $access) || !preg_match('/^[A-Za-z0-9]{6,16}$/', $secret)) {
    $reason = '凭据格式不合法：AccessKey / SecretKey 都必须是 6~16 位字母或数字。';
} elseif ($access === $secret) {
    $reason = '两条凭据不能是同一串字符：巡检要求它们确实来自两套不同的系统。';
} elseif (md5($access) == md5($secret)) {   // ← 漏洞点：宽松比较
    $passed = true;
}
```

三点设计上的取舍：

1. **格式约束是 6~16 位字母数字**，不是 8 位起步。经典 payload `QNKCDZO` 只有 7 位，若下限设成 8
   会把绝大多数入门 payload 挡在外面，难度会从 1 涨到 2 以上。约束的真实作用是挡掉超长输入和奇形
   怪状的 payload，而不是卡住新手。
2. **先 `is_string()` 再比较**：PHP 8 里 `md5(array)` 会抛 `TypeError`，提交 `access_key[]=1` 会变成
   500 而不是有意义的提示。加前置判断后，数组输入只会得到「必须是非空字符串」，PHP 7/8 行为一致。
3. **严格比较在前**：`$access === $secret` 先拦一次，确保选手必须真的给出两条不同字符串（而不是想
   办法绕过「不能相同」这一条）。

干扰项/边界：无数据库、无文件包含、无其它可利用点，页面回显均已 `htmlspecialchars()` 转义，
`robots.txt` 屏蔽 `/inc/` 与 `/audit.php` 只是氛围。

### 输入面与运行时加固（审计后行为）

- 接口只接受**表单与 URL 参数**（POST 优先），**Cookie 不作为输入源**。原因：`php:8.2-apache` 默认
  不带 php.ini，缺省 `variables_order=EGPCS`，此时 `$_REQUEST` 会把 Cookie 也算进去（甚至可以覆盖
  POST）。审计时实测过“只用 Cookie 提交也能通过对账”，所以 `audit.php` 改成了 `$_POST + $_GET`。
- Dockerfile 启用了 `php.ini-production`（`display_errors=Off` 不让 warning 泄露路径、
  `request_order=GP`），并用 `conf.d/zz-hardening.ini` 关掉 `expose_php`——production 模板里那行是
  注释状态，内置默认是 On，不关会在响应头暴露 PHP 版本。
- Apache 侧对 `/inc` 目录 `Require all denied`（直接请求返回 403），`Options -Indexes` 关闭目录列表。
- `inc/common.php` 在既没有 `/flag` 又没有平台注入时返回 `KEYVAULT_FLAG_NOT_READY`（不是形似 flag 的
  占位串），免得选手把占位符当答案提交。

### Dockerfile 里两个容易踩的坑

1. **`php:8.2-apache` 自带 `/var/www/html/index.html`**，Debian 默认的 `DirectoryIndex` 把
   `index.html` 排在 `index.php` 前面。不处理的话站点首页会显示 "It works!" 而不是题目首页
   （本地实测确认过）。本题在 `COPY` 之后 `rm -f /var/www/html/index.html`，并在站点 conf 里
   显式写 `DirectoryIndex index.php`（双保险）。
2. **`.gitattributes` 强制 `*.sh text eol=lf`**：`start.sh` 在 Windows 上编辑后带 CRLF 会让容器里的
   `/bin/bash` 报 `\r: command not found`。Dockerfile 里还额外 `sed -i 's/\r$//'` 兜底。

---

## 三、目录结构

```
md5-vault/
├── Dockerfile              # php:8.2-apache，关闭目录列表，COPY src/ 与 start.sh
├── start.sh                # 动态 Flag：GZCTF_FLAG → FLAG → 随机兜底，写入 /flag 后 unset
├── docker-compose.yml      # 本地自测：8082:80，注入测试 Flag
├── .dockerignore           # 不把题目文档打进镜像
├── .gitattributes          # *.sh 强制 LF，避免 Windows 编辑破坏容器内脚本
├── README.md               # 本文件（出题人用）
├── WRITEUP.md              # 解题过程
└── src/
    ├── index.php           # 对账表单 + 三条对账规则
    ├── audit.php           # 对账接口（GET/POST）
    ├── assets/style.css    # 暗色控制台样式
    ├── robots.txt
    └── inc/common.php      # 转义、页面骨架、Flag 读取
```

---

## 四、部署与自测

### 方式 1：docker compose（推荐，本地调试）

```bash
cd md5-vault
docker compose up -d --build     # 访问 http://127.0.0.1:8082/
```

自测 payload：

```bash
curl -s -X POST -d 'access_key=240610708&secret_key=QNKCDZO' \
     http://127.0.0.1:8082/audit.php
```

### 方式 2：docker build + docker run

```bash
cd md5-vault
docker build -t md5-vault:1.0 .
docker run -d --name md5_vault -p 8082:80 -e FLAG='flag{local_dev_flag}' md5-vault:1.0
docker logs md5_vault        # 看到 "[keyvault] Flag 已就绪（来源：FLAG（本地 / 自建平台注入）…）" 即为就绪
```

---

## 五、GZCTF 出题配置

| 配置项 | 值 |
| --- | --- |
| 挑战类型 | Dynamic Container（Web） |
| 代码仓库 | <https://github.com/dez-eng/md5-vault> |
| 容器镜像 | `ghcr.io/dez-eng/md5-vault:latest`（包设为 **public**，平台免配凭据即可拉取） |
| 容器端口 | `80` |
| Flag | 平台动态生成（**不要**在题目里填写静态 Flag）；Flag 模板按竞赛配置，如 `HuSec2026{[GUID]}` |
| 环境变量注入 | GZCTF 会自动向容器注入 `GZCTF_FLAG`，`start.sh` 会读取它 |

构建与推送（本机已登录 ghcr 时；日期 tag 按推送当天改）：

```bash
cd md5-vault
docker build -t ghcr.io/dez-eng/md5-vault:latest -t ghcr.io/dez-eng/md5-vault:20261007 .
docker push ghcr.io/dez-eng/md5-vault:latest
docker push ghcr.io/dez-eng/md5-vault:20261007
```

出题人自测须知：

- 管理端「测试容器」下发的是 **GZCTF 占位 Flag**（含 `dynamic_flag_test`），此时 `start.sh` 会打印
  `WARNING: 检测到 GZCTF 占位测试 Flag...`，这是预期行为，只有选手实例才是真实动态 Flag。
- Flag 只有 web 根之外的 `/flag` 一份，HTTP 直接请求 `/flag` 会 404；页面是唯一出口。
- 容器内环境变量在 Apache 启动前已 `unset`，`phpinfo()` 类泄露拿不到 Flag。

---

## 六、修复建议（讲解用）

```php
// 1. 用严格比较，直接比字符串内容
if (hash_equals(md5($access), md5($secret))) { ... }   // 仍不推荐：比摘要没有意义
// 2. 更好的做法：不比较摘要，直接比较凭据本身，并用 hash_equals 防时序侧信道
if (hash_equals($access, $secret)) { ... }
```

可讲的三个层次：`==` → `===`/`hash_equals()` → 根本不比较摘要（业务设计问题）。
