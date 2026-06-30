# Dynamic Auth Test Summary

- AUTH-001 | Client info without token | status=401 code=40100 | pass=true | 未登录或登录已过期
- AUTH-002 | Admin info without token | status=401 code=40100 | pass=true | 未登录或登录已过期
- AUTH-003 | Client token on admin endpoint | status=403 code=40300 | pass=true | 仅允许管理员访问
- AUTH-004 | Admin token on client endpoint | status=403 code=40300 | pass=true | 仅允许客户访问
- AUTH-005 | Tampered client token | status=401 code=40100 | pass=true | 未登录或登录已过期
- AUTH-006 | Reused logged-out client token | status=401 code=40100 | pass=true | 未登录或登录已过期
- AUTH-007 | Second concurrent client token remains valid after first logout | status=200 code=0 | pass=true | 操作成功
- AUTH-008 | Reused logged-out admin token | status=401 code=40100 | pass=true | 未登录或登录已过期
- AUTH-009 | Admin login-as first exchange | status=200 code=0 | pass=true | 代登录成功
- AUTH-010 | Admin login-as replay exchange | status=410 code=41000 | pass=true | 代登录凭证已失效，请重新发起