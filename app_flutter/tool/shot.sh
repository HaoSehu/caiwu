#!/bin/bash
# 真机截图工具：shot.sh <名称> [等待秒数]
# 用法：bash tool/shot.sh home 2
set -e
export MSYS_NO_PATHCONV=1
ADB="${ANDROID_HOME:-D:/dev/android-sdk}/platform-tools/adb.exe"
NAME="${1:-shot}"
WAIT="${2:-1}"
sleep "$WAIT"
"$ADB" shell screencap -p "/sdcard/_shot_$NAME.png"
OUT="$(cd "$(dirname "$0")/.." && pwd)/.shots"
mkdir -p "$OUT"
"$ADB" pull "/sdcard/_shot_$NAME.png" "$OUT/$NAME.png" >/dev/null
"$ADB" shell rm -f "/sdcard/_shot_$NAME.png"
echo "$OUT/$NAME.png"
