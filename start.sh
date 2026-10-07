#!/bin/bash
# KeyVault · 凭据对账服务启动脚本
# 负责把平台注入的动态 Flag 落到 web 根之外的 /flag，并从环境变量中清理掉
set -e

log() { echo "[keyvault] $*"; }

# ── 1. 取 Flag：GZCTF 注入 GZCTF_FLAG / 兼容 FLAG / 兜底随机 ────────────────
if [ -n "$GZCTF_FLAG" ]; then
    FLAG_SRC="GZCTF_FLAG（平台注入）"
    REAL_FLAG="$GZCTF_FLAG"
elif [ -n "$FLAG" ]; then
    FLAG_SRC="FLAG（本地 / 自建平台注入）"
    REAL_FLAG="$FLAG"
else
    FLAG_SRC="随机兜底（未检测到平台注入）"
    REAL_FLAG="flag{$(head -c 16 /dev/urandom | md5sum | cut -c1-16)}"
fi

# ── 2. 管理端「测试容器」的占位 Flag 检测，提醒出题人 ──────────────────────
case "$REAL_FLAG" in
    *dynamic_flag_test*)
        log "WARNING: 检测到 GZCTF 占位测试 Flag，这不是选手应看到的 Flag"
        log "WARNING: 请在竞赛管理端开启本挑战的动态 Flag 后再测试容器"
        ;;
esac

FLAG_LEN=${#REAL_FLAG}

# ── 3. 写入 /flag（web 根之外，HTTP 无法直接下载，PHP 可读）───────────────
# 不用 echo：避免追加尾部换行，选手取到的就是干净的一行 Flag
printf '%s' "$REAL_FLAG" > /flag
chmod 644 /flag

unset GZCTF_FLAG FLAG REAL_FLAG

log "Flag 已就绪（来源：$FLAG_SRC，长度：${FLAG_LEN}）"

exec apache2-foreground
