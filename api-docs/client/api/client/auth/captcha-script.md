# captcha-script

**请求方法**：GET  
**请求路径**：`/api/client/auth/captcha-script`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| 无 | - | 否 | 无请求参数 |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| body | string | 非 JSON 响应或空响应 |

### 返回示例（完整 JSON）
```json
{
    "raw_body": "(function (global) {\n    var SDK_URL = \"https://cdn4.vaptcha.com/src/v4.js\";\n    var VERIFY_PAGE_URL = 'https://cdn4.vaptcha.com/src/verify.html';\n    var sdkPromise = null;\n\n    function loadSdk() {\n        if (global.vaptcha) {\n            return Promise.resolve(global.vaptcha);\n        }\n\n        if (sdkPromise) {\n            return sdkPromise;\n        }\n\n        sdkPromise = new Promise(function (resolve, reject) {\n            var existing = document.querySelector('script[data-vaptcha-sdk=\"v4\"]');\n            if (existing) {\n                existing.addEventListener('load', function () { resolve(global.vaptcha); }, { once: true });\n                existing.addEventListener('error', function () { reject(new Error('VAPTCHA 脚本加载失败')); }, { once: true });\n                return;\n            }\n\n            var script = document.createElement('script');\n            script.src = SDK_URL;\n            script.async = true;\n            script.defer = true;\n            script.dataset.vaptchaSdk = 'v4';\n            script.onload = function () {\n                if (global.vaptcha) {\n                    resolve(global.vaptcha);\n                    return;\n                }\n\n                reject(new Error('VAPTCHA SDK 未初始化'));\n            };\n            script.onerror = function () { reject(new Error('VAPTCHA 脚本加载失败')); };\n            document.head.appendChild(script);\n        });\n\n        return sdkPromise;\n    }\n\n    function patchVerifyPageUrl(vaptchaObj) {\n        var core = global.VaptchaCore || (vaptchaObj && vaptchaObj.constructor);\n        if (!core || !core.prototype || typeof core.prototype.buildVerifyPageUrl !== 'function') {\n            return;\n        }\n        if (core.prototype.__caiwuVerifyPagePatch) {\n            return;\n        }\n\n        var original = core.prototype.buildVerifyPageUrl;\n        core.prototype.buildVerifyPageUrl = function (mode, display) {\n            var url = original.call(this, mode, display);\n            try {\n                var parsed...（已截断）",
    "error": null
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:22  
· 响应状态码：200  
· 调用方式：GET /api/client/auth/captcha-script  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\AuthController@captchaScript`  
· 请求校验：`无 FormRequest`  
· 响应结构：`控制器直接 `response()` 返回，需实际调用确认`  
· 中间件：`api`
