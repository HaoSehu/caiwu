/**
 * 财务快照系统盘兜底测试
 * 运行: node --experimental-strip-types ./tests/finance-snapshot.test.mjs
 */
import assert from 'node:assert/strict'

import { DEFAULT_SYSTEM_DISK_ITEM, DEFAULT_SYSTEM_DISK_LABEL, withDefaultSystemDisk } from '../finance/snapshotDefaults.ts'

// 1. 完整配置单（含 os）无系统盘 → 末尾追加默认行
{
  const snapshot = {
    base_amount: '128.00',
    items: [
      { field: 'area', label: '区域', value: 'hk', value_label: '香港', amount: '0.00' },
      { field: 'os', label: '操作系统', value: 'centos7', value_label: 'CentOS 7', amount: '0.00' },
      { field: 'memory', label: '内存', value: '2048', value_label: '2G', amount: '0.00' },
    ],
  }
  const result = withDefaultSystemDisk(snapshot)

  assert.equal(result.items.length, 4, '应追加一条系统盘行')
  const appended = result.items[3]
  assert.equal(appended.field, 'system_disk_size', '追加行 field 应为 system_disk_size')
  assert.equal(appended.label, '系统盘', '追加行 label 应为 系统盘')
  assert.equal(appended.value_label, 'Linux 30G / Windows 40G', '追加行应为默认系统盘口径')
  assert.equal('amount' in appended, false, '默认系统盘不单独计价，不应带金额')
}

// 2. 快照已携带 system_disk_size → 原样返回不追加
{
  const items = [
    { field: 'os', label: '操作系统', value: 'centos7', value_label: 'CentOS 7', amount: '0.00' },
    { field: 'system_disk_size', label: '系统盘', value: '40', value_label: '40G', amount: '0.00' },
  ]
  const snapshot = { items }
  const result = withDefaultSystemDisk(snapshot)

  assert.equal(result, snapshot, '已有系统盘行应原样返回同一对象')
  assert.equal(result.items.length, 2, '不应追加默认行')
}

// 3. 历史数据 field 非标准但展示名为「系统盘」→ 不追加（防双行与重复 key）
{
  const snapshot = {
    items: [
      { field: 'os', label: '操作系统', value: 'win', value_label: 'Windows', amount: '0.00' },
      { field: 'system_disk', label: '系统盘', value: '30', value_label: '30G', amount: '0.00' },
    ],
  }
  const result = withDefaultSystemDisk(snapshot)

  assert.equal(result, snapshot, '展示名已是系统盘的快照应原样返回')
}

// 4. 续费/升级/流量包快照（单条业务行、无 os 项）→ 不命中
{
  const renew = { items: [{ field: 'billing_cycle', label: '续费周期', value_label: 'monthly', amount: '99.00' }] }
  const upgrade = { items: [{ field: 'upgrade_product_id', label: '升降级商品', value_label: 'S2', amount: '50.00' }] }
  const traffic = { items: [{ field: 'flow_limit', label: '流量', value_label: '100G', amount: '10.00' }] }

  assert.equal(withDefaultSystemDisk(renew), renew, '续费快照不应补系统盘')
  assert.equal(withDefaultSystemDisk(upgrade), upgrade, '升级快照不应补系统盘')
  assert.equal(withDefaultSystemDisk(traffic), traffic, '流量包快照不应补系统盘')
}

// 5. 异常形态不崩溃：null/字符串/数组/空 items/元素非对象
{
  assert.deepEqual(withDefaultSystemDisk(null), {}, 'null 快照应返回空对象')
  assert.deepEqual(withDefaultSystemDisk('bad'), {}, '字符串输入应安全返回空对象')

  const arrayInput = ['x']
  assert.equal(withDefaultSystemDisk(arrayInput), arrayInput, '数组快照应原样返回不崩溃')

  const empty = withDefaultSystemDisk({ items: [] })
  assert.deepEqual(empty.items, [], '空 items 应原样保留')

  const nonObject = withDefaultSystemDisk({ items: ['str', 42] })
  assert.equal(nonObject.items.length, 2, '非对象 items 元素不应崩溃，也不应补行')
}

// 6. 纯函数：不修改原快照对象
{
  const snapshot = { items: [{ field: 'os', label: '操作系统', value: 'centos7', value_label: 'CentOS 7' }] }
  const before = JSON.stringify(snapshot)
  withDefaultSystemDisk(snapshot)

  assert.equal(JSON.stringify(snapshot), before, '原始快照对象不应被修改')
}

// 7. 默认值口径常量一致性
{
  assert.equal(DEFAULT_SYSTEM_DISK_LABEL, 'Linux 30G / Windows 40G', 'specs 缺省标签应与快照兜底同口径')
  assert.equal(DEFAULT_SYSTEM_DISK_ITEM.value_label, DEFAULT_SYSTEM_DISK_LABEL, '两个常量应同源')
  assert.equal(DEFAULT_SYSTEM_DISK_ITEM.field, 'system_disk_size', '默认行 field 应与后端范围型系统盘字段一致')
}

console.log('finance-snapshot tests passed')
