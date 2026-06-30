# 鐮斿彂澶嶇幇娓呭崟

娴嬭瘯鐜锛歚127.0.0.1:8000`  
璇佹嵁鐩綍锛歚security-report/20260627/`

## SEC-001 URL 浼犻€掑嚟璇?
1. 鎵撳紑 [frontend-user-v3-www/src/layout/WebsiteLayout.vue](../../../frontend-user-v3-www/src/layout/WebsiteLayout.vue)锛屾煡鐪?`onMounted()` 涓 `route.query._token` 鐨勫鐞嗐€?2. 鎵撳紑 [frontend-admin-v3/src/pages/users/detail/index.vue](../../../frontend-admin-v3/src/pages/users/detail/index.vue)锛屾煡鐪?`handleLoginAs()` 涓?`resolveLoginAsTarget()`銆?3. 鎵撳紑 [frontend-user-v4-console/src/pages/client-auth/login-as.vue](../../../frontend-user-v4-console/src/pages/client-auth/login-as.vue)锛屾煡鐪?`route.query.code` 鐨勬秷璐归€昏緫銆?4. 鍙傝€?`security-report/20260627/evidence/auth-dynamic-matrix.json` 涓?`response_samples.login_as_issue`锛岀‘璁ゆ湇鍔＄纭疄杩斿洖 `redirect_url` 涓斿甫 `code=`銆?
## SEC-002 VNC 椤佃法绐楀彛璇?token

1. 鎵撳紑 [frontend-user-v4-console/public/vnc/vnc.html](../../../frontend-user-v4-console/public/vnc/vnc.html)銆?2. 瀹氫綅 `getAuthToken()` 涓?`buildRequestHeaders()`銆?3. 纭浼氫緷娆¤鍙?`window`銆乣parent`銆乣opener` 鐨?`sessionStorage/localStorage` 涓殑 `admin_token/client_token`銆?
## SEC-003 浠ｇ櫥褰曟潈闄愪笌鍑瘉淇濇姢涓嶈冻

1. 鎵撳紑 [backend/routes/admin.php](../../../backend/routes/admin.php)锛岀‘璁?`/users/{user}/login-as` 鎸傚湪 `user.manage` 鏉冮檺缁勪笅銆?2. 鎵撳紑 [backend/app/Services/Auth/AuthService.php](../../../backend/app/Services/Auth/AuthService.php)锛屾煡鐪?`issueAdminLoginAsCode()` 鍜?`exchangeAdminLoginAsCode()`銆?3. 鍙傝€?`security-report/20260627/evidence/auth-dynamic-matrix.json` 涓?`AUTH-009/AUTH-010`锛岀‘璁わ細
   - 棣栨鍏戞崲鎴愬姛銆?   - 閲嶆斁鍏戞崲杩斿洖 `41000`銆?   - 杩斿洖瀵硅薄涓惈 `login_code` 鍜?`redirect_url`銆?
## SEC-004 瀹樼綉閫€鍑轰笉鎾ら攢鏈嶅姟绔?token

1. 鎵撳紑 [frontend-user-v3-www/src/stores/user.js](../../../frontend-user-v3-www/src/stores/user.js)锛岀‘璁?`logout()` 鍙墽琛?`removeToken()`銆?2. 瀵圭収 [frontend-user-v3-www/src/api/auth.js](../../../frontend-user-v3-www/src/api/auth.js)锛岀‘璁ゅ凡瀹氫箟 `clientAuthApi.logout()` 浣嗘湭琚?`logout()` 浣跨敤銆?3. 鍙傝€?`security-report/20260627/logs/auth-dynamic-summary.md` 涓?`AUTH-006`锛岀‘璁よ皟鐢ㄥ悗绔敞閿€鏃舵棫 token 浼氳鎷掔粷銆?
## SEC-005 鎺у埗鍙?401 鍚庢畫鐣欐寔涔呭寲鐢ㄦ埛鎬?
1. 鎵撳紑 [frontend-user-v4-console/src/utils/request.ts](../../../frontend-user-v4-console/src/utils/request.ts)锛岀‘璁?`redirectLogin()` 浠?`removeClientToken()`銆?2. 鎵撳紑 [frontend-user-v4-console/src/store/modules/user.ts](../../../frontend-user-v4-console/src/store/modules/user.ts)锛岀‘璁?`persist.pick = ['userInfo']`銆?3. 鎵撳紑 [frontend-user-v4-console/src/permission.ts](../../../frontend-user-v4-console/src/permission.ts)锛岀‘璁ゅ綋 `userInfo.name` 宸插瓨鍦ㄦ椂鍙烦杩?`getUserInfo()`銆?
## SEC-006 `/client/auth/info` 杩斿洖瀛楁杩囧

1. 鎵撳紑 [backend/app/Http/Controllers/Client/AuthController.php](../../../backend/app/Http/Controllers/Client/AuthController.php)銆?2. 鏌ョ湅 `info()` 杩斿洖鏁扮粍涓?`real_name`銆乣verification_certify_id`銆乣alipay_account`銆乣last_login_ip`銆佷綑棰濈被瀛楁銆?3. 瀵圭収 `security-report/20260627/evidence/auth-dynamic-matrix.json` 鐨?`client_auth_info_fields` 鍜?`response_samples.client_info`銆?
## 宸查獙璇侀€氳繃鐨勫熀绾?
1. 鍙傝€?`security-report/20260627/logs/auth-dynamic-summary.md`锛?   - `AUTH-001/AUTH-002`锛氭湭鐧诲綍璁块棶鍙椾繚鎶ゆ帴鍙ｈ鎷掔粷銆?   - `AUTH-003/AUTH-004`锛歚client/admin` token 涓嶈兘璺ㄨ鑹茶闂€?   - `AUTH-005`锛氱鏀?token 琚嫆缁濄€?   - `AUTH-006/AUTH-008`锛氭敞閿€鍚庡鐢?token 琚嫆缁濄€?2. 鍙傝€?`security-report/20260627/evidence/idor-and-sensitive-checks.json`锛?   - `invoices/payments/orders/services/ledger` 鐩搁偦 ID 鎺㈡祴鍧囪繑鍥?404銆?
