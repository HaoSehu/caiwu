# 缃戠珯璁よ瘉銆侀壌鏉冧笌鏁忔劅淇℃伅淇濇姢瀹夊叏娴嬭瘯鎶ュ憡

娴嬭瘯鏃ユ湡锛?026-06-27  
娴嬭瘯鐜锛歚local / 127.0.0.1:8000`锛屽墠绔鍙?`5173/5174/5175`  
娴嬭瘯鑼冨洿锛氳璇佹祦绋嬨€侀壌鏉冭竟鐣屻€佸鎴风淇′换銆乼oken 鐢熷懡鍛ㄦ湡銆佹晱鎰熷瓧娈佃繑鍥? 
娴嬭瘯璐﹀彿瑙掕壊锛氭祴璇曞鎴风璐﹀彿銆佹祴璇曠鐞嗗憳璐﹀彿  
璇佹嵁鏍圭洰褰曪細`security-report/20260627/`

## 娴嬭瘯缁撹

- 宸查獙璇侀€氳繃锛?  - 鏈櫥褰曡闂彈淇濇姢鎺ュ彛浼氳鎷掔粷銆?  - `client` token 涓嶈兘璁块棶 `admin` 璧勬簮锛宍admin` token 涓嶈兘璁块棶 `client` 璧勬簮銆?  - 绡℃敼 token銆佹敞閿€鍚庡鐢?token 浼氳鎷掔粷銆?  - 绠＄悊鍛樹唬鐧诲綍 `login_code` 涓轰竴娆℃€ф秷璐癸紝閲嶅鍏戞崲浼氬け鏁堛€?  - 鏈疆瀵?`invoices/payments/orders/services/ledger` 鐨勭浉閭?ID 鎺㈡祴鏈彂鐜板凡璇佸疄鐨勬í鍚戣秺鏉冦€?- 宸茬‘璁ら棶棰橈細
  - 鍙戠幇 3 涓珮椋庨櫓闂銆?  - 鍙戠幇 3 涓腑椋庨櫓闂銆?- 鏈鐩栨垨浠呴潤鎬佸璁★細
  - 鍙屽鎴风璐﹀彿鐨勫畬鏁?A->B 妯悜瓒婃潈銆?  - `VNC public token`銆乣secure-assets/view signed URL`銆佹敮浠樺洖璋冪鍚嶄笌閲嶆斁銆?  - 楠岃瘉鐮佺櫥褰曚笌鎵惧洖瀵嗙爜鐨勭湡瀹?GeeTest/鐭俊/閭欢閾捐矾銆?
## 娴嬭瘯鐭╅樀鎽樿

| 绫诲埆 | 缁撴灉 | 璇佹嵁 |
| --- | --- | --- |
| 璁よ瘉缁曡繃 | 鏈彂鐜板凡璇佸疄缁曡繃 | `evidence/auth-dynamic-matrix.json` |
| 璺ㄨ鑹茶秺鏉?| 鏈彂鐜?| `AUTH-003`, `AUTH-004` |
| token 绡℃敼/娉ㄩ攢澶嶇敤 | 鏈彂鐜?| `AUTH-005`, `AUTH-006`, `AUTH-008` |
| 浠ｇ櫥褰曚竴娆℃€ф秷璐?| 閫氳繃 | `AUTH-009`, `AUTH-010` |
| 妯悜瀵硅薄缁戝畾 | 鏈疆鏈彂鐜?| `evidence/idor-and-sensitive-checks.json` |
| 瀹㈡埛绔俊浠?鍑瘉鏆撮湶 | 鍙戠幇闂 | 瑙佷笅杩?`SEC-*` |
| 鏁忔劅瀛楁杩斿洖 | 鍙戠幇闂 | `SEC-006` |

## 鍙戠幇璇︽儏

### SEC-001

- 闂缂栧彿锛歚SEC-001`
- 椋庨櫓绛夌骇锛氶珮
- 娴嬭瘯鐜锛歚local / 127.0.0.1:5174, 5173`
- 娴嬭瘯璐﹀彿瑙掕壊锛氬鎴风銆佺鐞嗗憳
- 鍓嶇疆鏉′欢锛氬彲璁块棶瀹樼綉鍜屾帶鍒跺彴椤甸潰锛涚鐞嗗憳鍙墽琛屼唬鐧诲綍
- 鎿嶄綔姝ラ锛?  1. 瀹℃煡 [frontend-user-v3-www/src/layout/WebsiteLayout.vue](../../../frontend-user-v3-www/src/layout/WebsiteLayout.vue) 涓櫥褰曟€佹仮澶嶉€昏緫銆?  2. 瀹℃煡 [frontend-admin-v3/src/pages/users/detail/index.vue](../../../frontend-admin-v3/src/pages/users/detail/index.vue) 涓?[frontend-user-v4-console/src/pages/client-auth/login-as.vue](../../../frontend-user-v4-console/src/pages/client-auth/login-as.vue) 涓唬鐧诲綍璺宠浆閫昏緫銆?  3. 鍔ㄦ€佽皟鐢ㄧ鐞嗗憳浠ｇ櫥褰曟帴鍙ｏ紝瑙傚療杩斿洖鐨?`login_code` 鍜?`redirect_url`銆?- 棰勬湡缁撴灉锛?  - 鐧诲綍鍑瘉涓嶅簲閫氳繃 URL 鏌ヨ鍙傛暟浼犻€掓垨娑堣垂銆?  - 浠ｇ櫥褰曞嚟璇佷笉搴斿嚭鐜板湪娴忚鍣ㄥ巻鍙层€丷eferrer銆佷唬鐞嗘棩蹇楀彲瑙佺殑浣嶇疆銆?- 瀹為檯缁撴灉锛?  - 瀹樼綉浼氳鍙?URL 鏌ヨ鍙傛暟 `_token`锛屽苟鐩存帴鎶婅鍊兼寔涔呭寲涓虹櫥褰曟€併€?  - 绠＄悊鍛樹唬鐧诲綍杩斿洖鐨?`redirect_url` 涓?`/client/login-as?code=...`锛屾帶鍒跺彴椤甸潰鍐嶄粠 `route.query.code` 涓秷璐硅鍑瘉銆?- 鍏抽敭璇锋眰/鍝嶅簲锛屾晱鎰熷瓧娈佃劚鏁忥細
  - `POST /api/admin/users/{user}/login-as` 杩斿洖 `login_code=H9VQ********************************************************Ezhk`
  - 鍚屽搷搴旇繑鍥?`redirect_url=http://127.0.0.1:5173/client/login-as?code=H9VQ********************************************************Ezhk`
- 鎴浘鎴栨棩蹇楄矾寰勶細
  - `security-report/20260627/evidence/auth-dynamic-matrix.json`
  - 婧愮爜锛歚frontend-user-v3-www/src/layout/WebsiteLayout.vue:336-347`
  - 婧愮爜锛歚frontend-admin-v3/src/pages/users/detail/index.vue:1276-1302`
  - 婧愮爜锛歚frontend-user-v4-console/src/pages/client-auth/login-as.vue:66-79`
- 褰卞搷鑼冨洿锛?  - 瀹樼綉璺ㄧ鐧诲綍鎭㈠
  - 绠＄悊鍛樹唬鐧诲綍
  - 娴忚鍣ㄥ巻鍙层€佹棩蹇椼€佽烦杞摼璺€佹綔鍦?Referrer 娉勯湶闈?- 鏍瑰洜鎺ㄦ祴锛?  - 鍓嶇涓鸿法绔細璇濇仮澶嶅拰浠ｇ櫥褰曚究鎹锋€э紝鐩存帴閫夋嫨浜?URL 浼犻€掑嚟璇併€?- 淇寤鸿锛?  - 鍋滄浣跨敤 `_token` 鏌ヨ鍙傛暟鎭㈠鐧诲綍鎬併€?  - 浠ｇ櫥褰曟敼涓轰竴娆℃€?POST 浜ゆ崲鎴栫煭鏈熷悗绔腑杞〉锛屼笉鍦?URL 涓斁 `code`銆?  - 瀵瑰巻鍙查摼鎺ュ拰鏃ュ織鍋氭帓鏌ワ紝纭涓嶅瓨鍦ㄥ嚟璇佽惤鐩樸€?- 鍥炲綊娴嬭瘯寤鸿锛?  - 楠岃瘉鐧诲綍鎭㈠涓庝唬鐧诲綍閾捐矾涓嶅啀浣跨敤 URL 鍑瘉銆?  - 楠岃瘉娴忚鍣ㄥ湴鍧€鏍忋€佸巻鍙茶褰曘€佹棩蹇椾腑涓嶅嚭鐜?token/code銆?
### SEC-002

- 闂缂栧彿锛歚SEC-002`
- 椋庨櫓绛夌骇锛氶珮
- 娴嬭瘯鐜锛歚local / 127.0.0.1:5173`
- 娴嬭瘯璐﹀彿瑙掕壊锛氬鎴风銆佺鐞嗗憳
- 鍓嶇疆鏉′欢锛氬彲鎵撳紑 VNC 鐙珛椤垫垨鐩稿叧寮圭獥
- 鎿嶄綔姝ラ锛?  1. 瀹℃煡 [frontend-user-v4-console/public/vnc/vnc.html](../../../frontend-user-v4-console/public/vnc/vnc.html) 鐨?token 鑾峰彇閫昏緫銆?  2. 纭璇ラ〉浼氬皾璇曚粠 `window`銆乣parent`銆乣opener` 鐨?`sessionStorage/localStorage` 璇诲彇 `admin_token/client_token`銆?- 棰勬湡缁撴灉锛?  - 鐙珛椤典笉搴旇法绐楀彛銆佽法涓婁笅鏂囦富鍔ㄦ悳闆嗗苟澶嶇敤鐖堕〉闈?token銆?- 瀹為檯缁撴灉锛?  - VNC 椤典細浠庤嚜韬€佺埗绐楀彛銆佹墦寮€鑰呯獥鍙ｇ殑 `sessionStorage/localStorage` 鎼滅储 token锛屽苟鐩存帴鎷煎叆 `Authorization` 璇锋眰澶淬€?- 鍏抽敭璇锋眰/鍝嶅簲锛屾晱鎰熷瓧娈佃劚鏁忥細
  - 闈欐€佽瘉鎹細`Authorization: Bearer <parent/opener token>` 鐢?`buildRequestHeaders()` 缁勮銆?- 鎴浘鎴栨棩蹇楄矾寰勶細
  - 婧愮爜锛歚frontend-user-v4-console/public/vnc/vnc.html:536-562`
  - 婧愮爜锛歚frontend-user-v4-console/public/vnc/vnc.html:668-737`
- 褰卞搷鑼冨洿锛?  - VNC 寮圭獥銆乮frame銆佽法绐楀彛鍦烘櫙
  - 閬楃暀 localStorage token 澶嶆椿
  - 鍚屾簮鑴氭湰涓庡脊绐楅摼璺殑 token 娉勯湶闈?- 鏍瑰洜鎺ㄦ祴锛?  - 涓哄吋瀹瑰绐楀彛/VNC 浣跨敤鍦烘櫙锛屽湪鍓嶇寮曞叆浜嗚法涓婁笅鏂?token 鎺㈡祴閫昏緫銆?- 淇寤鸿锛?  - 鍒犻櫎瀵?`parent/opener` storage 鐨?token 鎼滅储銆?  - 鏀逛负鍚庣绛惧彂鏈€灏忔潈闄愩€佺煭鏃舵晥銆佷笓鐢ㄧ殑 VNC 璁块棶鍑瘉銆?  - 閬垮厤鍦?VNC 椤甸潰澶嶇敤绠＄悊/瀹㈡埛绔富鐧诲綍 token銆?- 鍥炲綊娴嬭瘯寤鸿锛?  - 楠岃瘉 VNC 椤典粎浣跨敤涓撶敤涓€娆℃€у嚟璇併€?  - 楠岃瘉鐖剁獥鍙ｆ垨 opener 涓殑 token 涓嶄細鍐嶈璇诲彇銆?
### SEC-003

- 闂缂栧彿锛歚SEC-003`
- 椋庨櫓绛夌骇锛氶珮
- 娴嬭瘯鐜锛歚local / 127.0.0.1:8000`
- 娴嬭瘯璐﹀彿瑙掕壊锛氱鐞嗗憳
- 鍓嶇疆鏉′欢锛氱鐞嗗憳鍏峰 `user.manage` 鏉冮檺
- 鎿嶄綔姝ラ锛?  1. 鍔ㄦ€佽皟鐢?`POST /api/admin/users/{user}/login-as`銆?  2. 瀹℃煡鍚庣 `issueAdminLoginAsCode()` 閫昏緫銆?  3. 瑙傚療鏈嶅姟绔繑鍥炵殑鍙洿鎺ヤ唬鐧诲鎴风鍑瘉銆?- 棰勬湡缁撴灉锛?  - 绠＄悊鍛樹唬鐧诲綍鑳藉姏搴斿叿澶囩嫭绔嬮珮鏁忔潈闄愬拰鏇村己淇濇姢锛屼笉搴斾粎渚濊禆鏅€氱敤鎴风鐞嗘潈闄愩€?- 瀹為檯缁撴灉锛?  - 浠ｇ櫥褰曡兘鍔涙寕鍦?`user.manage` 涓嬨€?  - 杩斿洖鍝嶅簲鐩存帴甯︿竴娆℃€?`login_code` 鍜?`redirect_url`銆?  - 鏈嶅姟绔敞閲婅鏄庡凡绉婚櫎 IP 缁戝畾锛屼粎鍓?TTL + 涓€娆℃€ф秷璐?+ UA hash銆?- 鍏抽敭璇锋眰/鍝嶅簲锛屾晱鎰熷瓧娈佃劚鏁忥細
  - `POST /api/admin/users/1/login-as -> 200`
  - 杩斿洖 `login_code=H9VQ********************************************************Ezhk`
  - 杩斿洖 `expires_in=120`
- 鎴浘鎴栨棩蹇楄矾寰勶細
  - `security-report/20260627/evidence/auth-dynamic-matrix.json`
  - 婧愮爜锛歚backend/routes/admin.php:97-103`
  - 婧愮爜锛歚backend/app/Services/Auth/AuthService.php:640-758`
- 褰卞搷鑼冨洿锛?  - 绠＄悊鍛樹唬鐧诲綍
  - 杩愯惀銆佸鏈嶃€佸悗鍙伴珮鏉冮檺璐﹀彿
- 鏍瑰洜鎺ㄦ祴锛?  - 浠ｇ櫥褰曡瑙嗕綔鏅€氱敤鎴风鐞嗙殑闄勫睘鑳藉姏锛屾病鏈夊崟鐙殑楂樻晱鎺堟潈鍜屼簩娆＄‘璁ゃ€?- 淇寤鸿锛?  - 涓?impersonation 澧炲姞鐙珛鏉冮檺锛屼緥濡?`user.login_as`銆?  - 澧炲姞浜屾纭鎴?step-up auth銆?  - 缁撳悎鍚庣涓浆椤垫浛浠ｇ洿鎺ヨ繑鍥炲彲娑堣垂鍑瘉銆?- 鍥炲綊娴嬭瘯寤鸿锛?  - 鏃?`user.login_as` 鏉冮檺鐨勭鐞嗗憳搴旀棤娉曚唬鐧诲綍銆?  - 浠ｇ櫥褰曟搷浣滃簲浜х敓瀹¤鏃ュ織骞跺甫鏄庣‘瀹℃壒/纭閾捐矾銆?
### SEC-004

- 闂缂栧彿锛歚SEC-004`
- 椋庨櫓绛夌骇锛氫腑
- 娴嬭瘯鐜锛歚local / 127.0.0.1:5174`
- 娴嬭瘯璐﹀彿瑙掕壊锛氬鎴风
- 鍓嶇疆鏉′欢锛氬畼缃戝凡鐧诲綍
- 鎿嶄綔姝ラ锛?  1. 瀹℃煡瀹樼綉 `logout()` 瀹炵幇銆?  2. 瀵圭収瀹樼綉 `clientAuthApi.logout` 瀹氫箟涓庡疄闄呰皟鐢ㄦ儏鍐点€?  3. 缁撳悎鍔ㄦ€侀獙璇佺殑澶?token 鍦烘櫙璇勪及鏈嶅姟绔?token 鐢熷懡鍛ㄦ湡銆?- 棰勬湡缁撴灉锛?  - 鍓嶇閫€鍑虹櫥褰曞簲璋冪敤鍚庣娉ㄩ攢鎺ュ彛锛岃嚦灏戞挙閿€褰撳墠鏈嶅姟绔?token銆?- 瀹為檯缁撴灉锛?  - 瀹樼綉 `logout()` 鍙竻鏈湴 `info` 鍜?token cookie锛屼笉璋冪敤 `/client/auth/logout`銆?  - 鍔ㄦ€侀獙璇佽瘉鏄庡崟 token 娉ㄩ攢鏄湇鍔＄鐢熸晥鐨勶紝浣嗗畼缃戝綋鍓嶆湭鎵ц璇ュ姩浣溿€?- 鍏抽敭璇锋眰/鍝嶅簲锛屾晱鎰熷瓧娈佃劚鏁忥細
  - 闈欐€佽瘉鎹細`frontend-user-v3-www/src/stores/user.js:26-29`
  - 鍔ㄦ€佸鐓э細`AUTH-006` 鏄剧ず璋冪敤鍚庣娉ㄩ攢鍚庯紝鏃?token 浼氳鎷掔粷銆?- 鎴浘鎴栨棩蹇楄矾寰勶細
  - `security-report/20260627/logs/auth-dynamic-summary.md`
  - 婧愮爜锛歚frontend-user-v3-www/src/stores/user.js:21-29`
  - 婧愮爜锛歚frontend-user-v3-www/src/api/auth.js:3-5`
- 褰卞搷鑼冨洿锛?  - 瀹樼綉閫€鍑虹櫥褰?  - 澶氭爣绛鹃〉銆佸悓鍩熷叾浠栭〉闈€佹湇鍔＄ token 娈嬬暀
- 鏍瑰洜鎺ㄦ祴锛?  - 瀹樼綉灏嗛€€鍑虹悊瑙ｄ负鏈湴鐧诲綍鎬佹竻鐞嗭紝鑰屼笉鏄湇鍔＄浼氳瘽鎾ら攢銆?- 淇寤鸿锛?  - 瀹樼綉閫€鍑虹粺涓€璋冪敤 `/client/auth/logout`銆?  - 澶辫触鏃跺啀鍋氭湰鍦板厹搴曟竻鐞嗭紝浣嗕笉鑳借烦杩囨湇鍔＄鎾ら攢銆?- 鍥炲綊娴嬭瘯寤鸿锛?  - 瀹樼綉閫€鍑哄悗锛屾棫 token 鍐嶈姹?`/api/client/auth/info` 搴旇繑鍥炴湭鐧诲綍銆?
### SEC-005

- 闂缂栧彿锛歚SEC-005`
- 椋庨櫓绛夌骇锛氫腑
- 娴嬭瘯鐜锛歚local / 127.0.0.1:5173`
- 娴嬭瘯璐﹀彿瑙掕壊锛氬鎴风
- 鍓嶇疆鏉′欢锛氭帶鍒跺彴瀛樺湪 `client_user` 鎸佷箙鍖栨暟鎹?- 鎿嶄綔姝ラ锛?  1. 瀹℃煡 `frontend-user-v4-console` 鐨?401 璺宠浆閫昏緫銆?  2. 瀹℃煡 `client_user` 鎸佷箙鍖栦笌璺敱瀹堝崼鍒锋柊鏉′欢銆?- 棰勬湡缁撴灉锛?  - 401 鎴?token 澶辨晥鍚庯紝搴斿悓鏃舵竻鐞嗘湰鍦版寔涔呭寲鐢ㄦ埛鎬侊紝骞跺己鍒堕噸鏂板悜鍚庣鎷夊彇銆?- 瀹為檯缁撴灉锛?  - 401 鏃朵粎鍒犻櫎 `client_token` 骞惰烦鐧诲綍椤碉紝娌℃湁鍚屾娓呯悊 `client_user`銆?  - 璺敱瀹堝崼浠?`userInfo.name` 浣滀负鏄惁璺宠繃 `getUserInfo()` 鐨勬潯浠讹紝瀛樺湪浣跨敤鏈湴闄堟棫鎴栬绡℃敼鐢ㄦ埛鎬侀┍鍔?UI 鐨勯闄┿€?- 鍏抽敭璇锋眰/鍝嶅簲锛屾晱鎰熷瓧娈佃劚鏁忥細
  - 闈欐€佽瘉鎹細`removeClientToken()` 鍚庣洿鎺?`router.push('/client/login')`
  - 闈欐€佽瘉鎹細鎸佷箙鍖栧彧淇濆瓨 `userInfo`
- 鎴浘鎴栨棩蹇楄矾寰勶細
  - 婧愮爜锛歚frontend-user-v4-console/src/utils/request.ts:63-72`
  - 婧愮爜锛歚frontend-user-v4-console/src/store/modules/user.ts:82-105`
  - 婧愮爜锛歚frontend-user-v4-console/src/permission.ts:72-88`
- 褰卞搷鑼冨洿锛?  - 鎺у埗鍙颁釜浜鸿祫鏂欍€佷綑棰濄€佸疄鍚嶇姸鎬併€佽仈绯绘柟寮忕瓑鍓嶇灞曠ず
- 鏍瑰洜鎺ㄦ祴锛?  - 鍓嶇鎶?UI 鎭㈠閫熷害鏀惧湪鏈嶅姟绔姸鎬佷竴鑷存€т箣鍓嶃€?- 淇寤鸿锛?  - 401 鏃跺悓姝ユ竻鐞?`client_user`銆?  - 璺敱瀹堝崼涓嶈浠ユ湰鍦?`userInfo.name` 浠ｆ浛鏈嶅姟绔‘璁ゃ€?- 鍥炲綊娴嬭瘯寤鸿锛?  - 浼€犳垨娈嬬暀鐨?`client_user` 涓嶅簲鍦ㄦ棤鏈夋晥 token 鏃剁户缁┍鍔ㄦ帶鍒跺彴椤甸潰灞曠ず銆?
### SEC-006

- 闂缂栧彿锛歚SEC-006`
- 椋庨櫓绛夌骇锛氫腑
- 娴嬭瘯鐜锛歚local / 127.0.0.1:8000`
- 娴嬭瘯璐﹀彿瑙掕壊锛氬鎴风
- 鍓嶇疆鏉′欢锛氬鎴风宸茬櫥褰?- 鎿嶄綔姝ラ锛?  1. 鍔ㄦ€佽姹?`GET /api/client/auth/info`銆?  2. 瀹℃煡杩斿洖瀛楁鍜屾帶鍒跺櫒瀹炵幇銆?- 棰勬湡缁撴灉锛?  - 璁よ瘉淇℃伅鎺ュ彛浠呰繑鍥炲綋鍓嶉〉闈㈢湡瀹為渶瑕佺殑鏈€灏忓瓧娈碉紝涓嶅簲榛樿杩斿洖杩囧鐨勮韩浠戒笌閲戣瀺淇℃伅銆?- 瀹為檯缁撴灉锛?  - `/api/client/auth/info` 杩斿洖浜?`real_name`銆乣id_card_masked`銆乣verification_certify_id`銆乣alipay_account`銆乣last_login_ip`銆佷綑棰濅笌鎺ㄨ崘鏀剁泭鐩稿叧瀛楁銆?- 鍏抽敭璇锋眰/鍝嶅簲锛屾晱鎰熷瓧娈佃劚鏁忥細
  - 瑙?`client_auth_info_fields` 涓?`response_samples.client_info`
- 鎴浘鎴栨棩蹇楄矾寰勶細
  - `security-report/20260627/evidence/auth-dynamic-matrix.json`
  - 婧愮爜锛歚backend/app/Http/Controllers/Client/AuthController.php:208-253`
- 褰卞搷鑼冨洿锛?  - 瀹㈡埛绔細璇濆垵濮嬪寲
  - 娴忚鍣ㄧ PII/閲戣瀺瀛楁鏆撮湶闈?- 鏍瑰洜鎺ㄦ祴锛?  - 灏嗕釜浜轰腑蹇冩墍闇€淇℃伅鍜屽熀纭€璁よ瘉浼氳瘽淇℃伅娣峰湪涓€涓帴鍙ｄ腑杩斿洖銆?- 淇寤鸿锛?  - 灏?`/client/auth/info` 鏀舵暃涓烘渶灏忎細璇濆瓧娈点€?  - 灏嗗疄鍚嶃€佹敮浠樸€佽储鍔＄瓑瀛楁鎷嗗埌鎸夐渶鎺ュ彛銆?- 鍥炲綊娴嬭瘯寤鸿锛?  - 鏍稿鏈櫥褰曘€佸凡鐧诲綍銆佷釜浜轰腑蹇冪瓑涓嶅悓椤甸潰鎵€闇€瀛楁锛岀‘淇濆彧鎸夐渶杩斿洖銆?
## 鏈瘉瀹為棶棰樹笌璇存槑

- 鏈疆鏈瘉瀹炶璇佺粫杩囥€?- 鏈疆鏈瘉瀹?`client` 涓?`admin` token 鍙法瑙掕壊璁块棶銆?- 鏈疆瀵?`invoices/payments/orders/services/ledger` 鐨勭浉閭?ID 鎺㈡祴鍧囪繑鍥?404锛屾湭鍙戠幇宸茶瘉瀹炴í鍚戣秺鏉冦€?- 鐢变簬浠呬娇鐢ㄤ竴涓鎴风娴嬭瘯璐﹀彿锛孉 鐢ㄦ埛璁块棶 B 鐢ㄦ埛鏁版嵁鐨勫畬鏁村弻璐﹀彿楠岃瘉鏈畬鎴愩€?
## 璇佹嵁娓呭崟

- `security-report/20260627/evidence/auth-dynamic-matrix.json`
- `security-report/20260627/evidence/idor-and-sensitive-checks.json`
- `security-report/20260627/logs/auth-dynamic-summary.md`
- `security-report/20260627/logs/auth-dynamic-tests.json`
- `security-report/20260627/logs/route-list-client.txt`
- `security-report/20260627/logs/route-list-admin.txt`
