import assert from 'node:assert/strict'
import {
  formatTicketServiceOptionLabel,
  resolveTicketServiceStatusMeta,
} from '../src/features/tickets/serviceOptionLabel.js'

assert.equal(
  formatTicketServiceOptionLabel({
    id: 1024,
    name: '2 vCPU 2G / 美国1区精品网 2H2G',
    status_label: '已开通',
  }),
  '#1024-美国1区精品网 2H2G-已开通',
  '工单关联服务应显示为“#实例id-实例名称-服务状态”',
)

assert.equal(
  formatTicketServiceOptionLabel({
    id: 2048,
    name: '香港云主机',
    status: 2,
  }),
  '#2048-香港云主机-已暂停',
  '当接口未返回 status_label 时，应回退到共享状态映射',
)

assert.equal(
  formatTicketServiceOptionLabel({
    id: 89,
    name: '2 vCPU 2G / yy的机器',
    status: 1,
    status_label: '已开通',
  }),
  '#89-yy的机器-已开通',
  '服务状态文案应优先使用接口返回的 status_label',
)

assert.equal(
  formatTicketServiceOptionLabel({
    id: 97,
    name: '2 vCPU 2G / 美国1区精品网 2H2G',
    status: 4,
    status_label: '已取消',
  }),
  '#97-美国1区精品网 2H2G-已取消',
  '服务状态文案应优先使用接口返回的 status_label',
)

assert.deepEqual(
  resolveTicketServiceStatusMeta({
    status: 2,
    status_label: '已暂停',
  }),
  {
    label: '已暂停',
    tagType: 'purple',
    elTagType: 'info',
    dot: true,
  },
  '应返回可用于状态标签展示的配色元信息',
)

assert.deepEqual(
  resolveTicketServiceStatusMeta({
    status: 4,
    status_label: '已取消',
  }),
  {
    label: '已取消',
    tagType: 'info',
    elTagType: 'info',
    dot: true,
  },
  '当接口返回 status_label 时，应直接信任后端文案并保留对应标签类型',
)

assert.equal(
  formatTicketServiceOptionLabel({
    id: 3001,
    name: '2 vCPU 2G / 自定义实例',
    status: 1,
    status_label: '后端已开通',
  }),
  '#3001-自定义实例-后端已开通',
  '当接口返回 status_label 时，应直接使用后端文案而不是本地映射文案',
)

assert.deepEqual(
  resolveTicketServiceStatusMeta({
    status: 1,
    status_label: '后端已开通',
  }),
  {
    label: '后端已开通',
    tagType: 'success',
    elTagType: 'success',
    dot: true,
  },
  '当接口返回 status_label 时，应沿用状态码对应的配色，但文案直接信任后端返回',
)

assert.equal(
  formatTicketServiceOptionLabel({
    id: 0,
    name: '',
    status: 999,
  }),
  '--',
  '缺少有效实例 id 和名称时应回退为占位文本',
)

console.log('ticketServiceOptionLabel tests passed')
