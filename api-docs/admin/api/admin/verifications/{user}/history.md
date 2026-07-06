# 实名历史记录

**请求方法**：GET  
**请求路径**：`/api/admin/verifications/{user}/history`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.user_name | string | 真实调用返回字段 |
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.real_name | string | 真实调用返回字段 |
| data.list.id_card_masked | string | 真实调用返回字段 |
| data.list.verification_status | integer | 真实调用返回字段 |
| data.list.verification_message | string | 真实调用返回字段 |
| data.list.verification_certify_id | string | 真实调用返回字段 |
| data.list.verification_method_label | string | 真实调用返回字段 |
| data.list.verification_type_label | string | 真实调用返回字段 |
| data.list.submitted_at | string | 真实调用返回字段 |
| data.list.completed_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "user_name": "李维佳",
        "list": [
            {
                "id": 90,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 23:47:07",
                "completed_at": "2026-07-04 23:49:47"
            },
            {
                "id": 89,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 3,
                "verification_message": "实名认证未通过，请核对后重试",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 23:45:30",
                "completed_at": "2026-07-04 23:47:02"
            },
            {
                "id": 88,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 3,
                "verification_message": "实名认证未通过，请核对后重试",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 23:37:27",
                "completed_at": "2026-07-04 23:40:21"
            },
            {
                "id": 87,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "1",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 23:31:43",
                "completed_at": "2026-07-04 23:31:43"
            },
            {
                "id": 86,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 21:11:18",
                "completed_at": "2026-07-04 21:12:04"
            },
            {
                "id": 85,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 4,
                "verification_message": "等待认证中",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 20:41:15",
                "completed_at": null
            },
            {
                "id": 84,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "1",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-04 20:10:52",
                "completed_at": "2026-07-04 20:10:52"
            },
            {
                "id": 83,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-02 02:38:44",
                "completed_at": "2026-07-02 02:39:19"
            },
            {
                "id": 82,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "1",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-07-02 02:25:37",
                "completed_at": "2026-07-02 02:25:37"
            },
            {
                "id": 74,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-06-24 01:25:04",
                "completed_at": "2026-06-24 01:27:24"
            },
            {
                "id": 46,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "1",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-06-01 16:19:54",
                "completed_at": "2026-06-01 16:19:54"
            },
            {
                "id": 28,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-04-26 15:37:48",
                "completed_at": "2026-04-26 15:39:11"
            },
            {
                "id": 23,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "1",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-04-21 15:25:31",
                "completed_at": "2026-04-21 15:25:31"
            },
            {
                "id": 20,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-04-19 03:07:49",
                "completed_at": "2026-04-19 03:10:04"
            },
            {
                "id": 19,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "1",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-04-19 03:07:32",
                "completed_at": "2026-04-19 03:07:32"
            },
            {
                "id": 8,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 2,
                "verification_message": "审核通过",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-03-28 14:52:06",
                "completed_at": "2026-03-28 14:52:50"
            },
            {
                "id": 7,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 4,
                "verification_message": "等待认证",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-03-28 14:24:08",
                "completed_at": null
            },
            {
                "id": 6,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 3,
                "verification_message": "认证失败",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-03-28 13:57:51",
                "completed_at": "2026-03-28 14:24:06"
            },
            {
                "id": 5,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 3,
                "verification_message": "认证失败",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-03-28 13:45:46",
                "completed_at": "2026-03-28 13:57:48"
            },
            {
                "id": 4,
                "real_name": "李维佳",
                "id_card_masked": "***已脱敏***",
                "verification_status": 5,
                "verification_message": "已解绑",
                "verification_certify_id": "***已脱敏***",
                "verification_method_label": "人脸识别",
                "verification_type_label": "个人认证",
                "submitted_at": "2026-03-28 03:44:24",
                "completed_at": "2026-03-28 03:44:24"
            }
        ]
    },
    "timestamp": 1783240521
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:21  
· 响应状态码：200  
· 调用方式：GET /api/admin/verifications/{user}/history  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\VerificationController@history`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:verification.list`
