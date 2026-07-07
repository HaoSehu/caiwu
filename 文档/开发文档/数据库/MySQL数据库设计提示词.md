# MySQL 数据库设计提示词（喂给 AI 用）

> 使用方式：将本文粘贴给 AI，附上你的业务需求描述，让 AI 按此规范输出 DDL。

---

## 一、总则：好设计的四个标准

1. **去冗余**：同一事实只存一处。派生数据可缓存但必须有唯一源头。
2. **表复用**：同类概念用一张表 + 类型字段区分，不为"三级分类""四种日志"建四张结构一样的表。
3. **易维护**：接手的人看表名和字段名就知道是什么意思，不用翻代码。
4. **人看得懂**：宁可多写两行 COMMENT，不要让人猜字段含义。

---

## 二、命名规范

### 2.1 通用规则

- 表名：`snake_case`，复数或单数统一即可，不用纠结。用**名词**，不用动词。
- 字段名：`snake_case`，全小写，不用拼音，不用缩写（除非缩写比全称更出名，如 `id`、`url`、`ip`）。
- 主键：统一 `id`，`BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`。
- 外键：`{关联表单数名}_id`，如 `user_id`、`order_id`。
- 时间字段：
  - `created_at` / `updated_at`：所有业务表标配，`TIMESTAMP NULL DEFAULT NULL`。
  - `deleted_at`：需要软删除的表加，`TIMESTAMP NULL DEFAULT NULL`。
  - 业务时间不加 `_at` 后缀的别名，如 `paid_at`、`expired_at`。
- 布尔/状态字段：`TINYINT`，不要用 `ENUM`。COMMENT 里写明每个值的含义，如 `0=禁用 1=启用`。
- 金额字段：`DECIMAL(12,2)`，不要用 `FLOAT` / `DOUBLE`。

### 2.2 索引命名

- 普通索引：`idx_{表简称}_{字段1}_{字段2}`
- 唯一索引：`uniq_{表简称}_{字段1}_{字段2}`
- 外键索引随外键约束自动创建即可，不用手动加。
- 不要在 `(a,b)` 上同时建 `INDEX(a)` 和 `INDEX(a,b)`，保留后者即可。

### 2.3 外键约束

- 必须声明 `FOREIGN KEY`，ON DELETE 按业务语义选 `RESTRICT` / `CASCADE` / `SET NULL`。
- ON UPDATE 统一 `NO ACTION`。
- 禁止只靠应用层保证引用完整性。

---

## 三、范式与表复用：核心原则

### 3.1 第一范式（1NF）：字段原子性

- 一个字段只存一个值。
- **禁止**用逗号拼接多个 ID（如 `product_ids = "1,3,7"`），应拆为关联表。
- **禁止**在 `VARCHAR` 里存 JSON 当作"灵活字段"——要么用真正的 `JSON` 类型（且有明确 schema），要么拆成独立列或关联表。

### 3.2 第二范式（2NF）：消除部分依赖

- 复合主键表（很少出现）：每个非主键字段必须依赖全部主键，不能只依赖其中一部分。

### 3.3 第三范式（3NF）：消除传递依赖

- 非主键字段不能依赖另一个非主键字段。
- 典型错误示例：`orders` 表里同时有 `product_id`、`product_name`、`product_price`。`product_name` 应通过 `product_id` 去 `products` 表查。

### 3.4 表复用模式（去冗余的核心）

遇到以下场景，用一张表解决问题，不要无脑建多张：

#### 场景 A：层级分类（如商品一级/二级/三级分组）

**错误做法**：`first_product_groups`、`second_product_groups`、`third_product_groups` 三张结构一样的表。

**正确做法**：一张 `product_groups` 表 + `parent_id` 自引用：

```sql
CREATE TABLE product_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL COMMENT '上级分组ID，NULL表示顶级',
    name VARCHAR(100) NOT NULL COMMENT '分组名称',
    slug VARCHAR(100) NULL COMMENT 'URL标识',
    description VARCHAR(255) NULL,
    banner_image VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=隐藏 1=可见',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE INDEX uniq_pg_parent_slug (parent_id, slug),
    INDEX idx_pg_parent_visible_sort (parent_id, is_visible, sort_order),
    CONSTRAINT fk_pg_parent FOREIGN KEY (parent_id) REFERENCES product_groups(id) ON DELETE RESTRICT
) COMMENT='商品分组（支持无限层级）';
```

深度用 CTE 递归查询，不要硬编码三级。

#### 场景 B：同类型日志/记录（如邮件日志、短信日志、通知日志）

**错误做法**：`email_logs`、`sms_logs`、`notification_logs` 三张结构 80% 相似的表。

**正确做法**：一张 `message_logs` 表 + `channel` 字段：

```sql
CREATE TABLE message_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(20) NOT NULL COMMENT '渠道: email/sms/wechat/push',
    plugin_id BIGINT UNSIGNED NULL,
    driver_key VARCHAR(120) NULL,
    trace_id VARCHAR(64) NULL,
    template_code VARCHAR(50) NULL,
    recipient VARCHAR(255) NOT NULL COMMENT '接收者（邮箱/手机号/openid）',
    subject VARCHAR(255) NULL COMMENT '标题（短信/null）',
    content TEXT NULL COMMENT '消息内容',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_msg TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_ml_channel_created (channel, created_at),
    INDEX idx_ml_recipient (recipient),
    INDEX idx_ml_trace (trace_id),
    CONSTRAINT fk_ml_plugin FOREIGN KEY (plugin_id) REFERENCES integration_plugins(id) ON DELETE SET NULL
) COMMENT='消息发送日志（邮件/短信/通知统一记录）';
```

如果不同渠道真有不同的必填字段（如短信没有 `subject`），用可空列 + COMMENT 说明即可，不值得为此拆表。

#### 场景 C：状态机/工作流（如工单回复、审批记录）

用一张流水表 + `action` 字段，不要每种操作建一张表。

#### 场景 D：快照/历史版本（如服务连接快照、运行时快照）

如果结构一致，用一张 `snapshots` 表 + `snapshot_type` 字段。只在索引膨胀或写入热点明显分离时才拆。

### 3.5 多态关联的正确姿势

当一个关联字段可能指向多种表时，使用标准多态模式：

```sql
-- 主体可以是多种类型
subject_type VARCHAR(50) NOT NULL COMMENT '关联对象类型: invoice/service/order/ticket',
subject_id BIGINT UNSIGNED NOT NULL COMMENT '关联对象ID',
INDEX idx_xx_subject (subject_type, subject_id)
```

- `*_type` 的值必须写进 COMMENT 里。
- 多态字段禁止设外键约束（MySQL 不支持）。
- 整个项目统一用 `{thing}_type` / `{thing}_id` 命名，不要这里叫 `source_type` 那里叫 `origin_type` 表示同一个概念。

---

## 四、审计字段规范

以下字段是项目级约定，需要审计的业务表统一携带，不要每张表各起一套名字：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `operator` | `VARCHAR(50) NULL` | 操作人标识/姓名快照 |
| `trace_id` | `VARCHAR(64) NULL` | 链路追踪ID，关联请求日志 |
| `remark` | `VARCHAR(255) NULL` | 人工备注 |
| `ip_address` | `VARCHAR(45) NULL` | 操作来源IP（IPv4/IPv6） |

日志专用表额外携带：
- `actor_type` / `actor_id` / `actor_name`：操作者信息。
- `action` / `module`：操作描述。

一套项目的审计字段名必须全局一致，禁止这张叫 `operator` 那张叫 `operator_name`。

---

## 五、JSON 字段的使用边界

JSON 字段是双刃剑。只在以下情况使用：

### 可以用的场景
1. **结构化配置**：如商品定价 `{"monthly": 29.00, "quarterly": 79.00}`、权限列表 `["user.read", "user.write"]`。
2. **快照/审计**：如订单生成时的商品配置快照 `config_snapshot`——保存下单瞬间的状态，防止后续商品变更影响历史。
3. **第三方原始数据**：如支付回调原始 payload —— 你不控制结构，保留原件即可。

### 禁止的场景
1. **替代关联表**：如用 JSON 数组存 `product_ids` 代替关联表。
2. **替代可枚举字段**：如用 JSON `{"color": "red"}` 代替 `color VARCHAR(20)`。
3. **替代真正的关系建模**：如果你发现自己频繁 `JSON_EXTRACT` 或 `JSON_CONTAINS` 做查询条件，说明该拆表了。

### JSON 字段必须的约束
- COMMENT 中描述 JSON 内 key 的含义和类型。
- 用法示例写在 COMMENT 中更好：`COMMENT '周期价格JSON，如 {"monthly":29.00,"quarterly":79.00}'`。
- 如果 JSON 结构稳定且有查询需求，优先拆为独立列。

---

## 六、索引设计原则

### 6.1 必须建索引的场景
- WHERE 条件列
- JOIN 关联列（外键）
- ORDER BY 列（尤其是复合排序）
- 唯一约束列

### 6.2 索引顺序
复合索引最左前缀原则：等值查询列在前，范围查询列在后，排序列在最后。
例如：`INDEX idx_xxx (status, user_id, created_at)` 适合 `WHERE status=1 AND user_id=? ORDER BY created_at DESC`。

### 6.3 不要做的事
- 不要在低基数列（如 `status TINYINT` 只有 0/1/2）上建单列索引，应作为复合索引前缀。
- 不要每列各建一个索引——不是索引越多越好，写入会变慢。
- 不要重复建 `INDEX(a)` 和 `INDEX(a,b)`，后者已经覆盖前者。
- 长 `VARCHAR`（>100）只建前缀索引或用全文索引。

---

## 七、表结构自检清单

每当你输出一张表的 DDL 后，自问：

1. **这张表和已有表有 80% 相同的列吗？** → 考虑合并，加一个 `type` 字段。
2. **有字段存了逗号分隔的多个值吗？** → 拆关联表。
3. **有字段可以从另一个字段推导出来吗？** → 删掉冗余字段（如 `total_price` = `unit_price * quantity` 不应同时存）。
4. **每个字段都有 COMMENT 吗？** → 没有就补。
5. **枚举值的含义写在 COMMENT 里了吗？** → 如 `status TINYINT NOT NULL DEFAULT 0 COMMENT '0=待处理 1=处理中 2=已完成 3=已取消'`。
6. **金额用了 DECIMAL 吗？** → 不是就改。
7. **状态用了 TINYINT 不是 ENUM 吗？** → ENUM 改 TINYINT。
8. **外键声明了吗？** → 补 `FOREIGN KEY ... REFERENCES`。
9. **索引有冗余吗？** → 删掉被复合索引覆盖的单列索引。
10. **表的 CHARSET 和 COLLATION 统一吗？** → 统一 `utf8mb4` + `utf8mb4_unicode_ci`。

---

## 八、反面教材（来自真实项目教训）

以下模式**禁止出现**：

### ❌ 三级分类建三张表
```sql
-- 错误：first_product_groups、second_product_groups、third_product_groups 三张结构几乎一样的表
-- 正确：一张 product_groups + parent_id 自引用
```

### ❌ 模板和实例字段重复
```sql
-- 错误：campaigns 定义 discount_type/discount_value/min_amount，
--       coupons 又复制一遍同样的字段
-- 正确：coupons 只存差异字段（如 code、used_count、expires_at），
--       公共字段 JOIN campaigns 获取
```

### ❌ 用 JSON 数组存关联
```sql
-- 错误：product_ids JSON  COMMENT '["1","3","7"]'
-- 正确：建 coupon_products (coupon_id, product_id) 关联表
```

### ❌ 日志表发散
```sql
-- 错误：email_logs(13列)、sms_logs(13列)、notification_logs(12列) 三张独立表
-- 正确：一张 message_logs + channel 字段
```

### ❌ 审计字段各表不一致
```sql
-- 错误：A 表 operator VARCHAR(50)、B 表 operator_name VARCHAR(100)、C 表 operator_id BIGINT
-- 正确：统一 operator VARCHAR(50) NULL COMMENT '操作人快照'
```

---

## 九、输出格式要求

当要求你输出 DDL 时，请按以下格式：

1. 每个 `CREATE TABLE` 用 `-- ---{表名}---` 分隔。
2. 表 COMMENT 必须写，一句话说明这张表存什么。
3. 每个字段 COMMENT 必须写，枚举值把含义列全。
4. 索引和外键约束紧跟在字段定义后面。
5. 使用 `IF NOT EXISTS` 避免重复执行报错。
6. 表前用 `DROP TABLE IF EXISTS` 仅在明确要求重建时使用。

示例输出片段：

```sql
-- ---invoices---
CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '账单自增主键',
    user_id BIGINT UNSIGNED NOT NULL COMMENT '所属用户ID',
    order_id BIGINT UNSIGNED NULL COMMENT '关联订单ID',
    invoice_no VARCHAR(64) NOT NULL COMMENT '账单编号',
    type VARCHAR(30) NOT NULL COMMENT '账单类型: purchase/renew/upgrade/recharge/refund',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '账单金额',
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0=待支付 1=已支付 2=已取消 3=已退款',
    paid_at TIMESTAMP NULL COMMENT '支付完成时间',
    operator VARCHAR(50) NULL COMMENT '操作人快照',
    trace_id VARCHAR(64) NULL COMMENT '链路追踪号',
    remark VARCHAR(255) NULL COMMENT '备注',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE INDEX uniq_inv_no (invoice_no),
    INDEX idx_inv_user_status (user_id, status, created_at),
    INDEX idx_inv_order (order_id),
    INDEX idx_inv_trace (trace_id),
    CONSTRAINT fk_inv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inv_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账单主表';
```

---

## 十、优先复用思维

设计任何新表之前，先问自己：

1. 现有 61 张表里有没有能复用的？（先查 `当前数据库结构.md`）
2. 能不能加一个 `type` 列让现有表覆盖新场景？
3. 如果是"xxx 日志""xxx 快照""xxx 记录"类需求，能否归入已有日志/快照体系？

**宁可在一张表上加字段，也不要建一张 90% 相似的新表。**
