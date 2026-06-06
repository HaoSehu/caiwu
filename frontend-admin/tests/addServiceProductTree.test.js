import assert from 'node:assert/strict'

import { buildAddServiceProductTree } from '../src/views/admin/Users/detail/composables/addServiceProductTree.js'

function testKeepsSecondAndThirdCategoryLevelsBeforeProductNodes() {
  const tree = buildAddServiceProductTree([
    {
      id: 10,
      name: '襄阳',
      product_type_label: '云服务器',
      children: [
        { id: 20, name: '高宽', children: [] },
      ],
    },
  ], [
    {
      id: 117,
      product_group_id: 20,
      name: 'gscs',
      display_name: 'gscs',
      cpu_memory_display: '2 vCPU 4G',
    },
  ])

  assert.equal(tree.length, 1)
  assert.equal(tree[0].label, '云服务器')
  assert.equal(tree[0].children[0].label, '襄阳')
  assert.equal(tree[0].children[0].disabled, true)
  assert.equal(tree[0].children[0].children[0].label, '高宽')
  assert.equal(tree[0].children[0].children[0].disabled, true)
  assert.deepEqual(tree[0].children[0].children[0].children[0], {
    value: 117,
    label: '2vcpu4gib',
    isProduct: true,
    leaf: true,
  })
}

function testDropsEmptyCategoryBranches() {
  const tree = buildAddServiceProductTree([
    {
      id: 10,
      name: '襄阳',
      product_type_label: '云服务器',
      children: [
        { id: 20, name: '高宽', children: [] },
      ],
    },
  ], [])

  assert.deepEqual(tree, [])
}

function testMatchesProductsByPublicGroupIdFromCategoryResource() {
  const tree = buildAddServiceProductTree([
    {
      id: 10,
      group_id: 10010,
      name: '轻量云',
      product_type_label: '云服务器',
      children: [
        { id: 20, group_id: 10020, name: '美国', children: [] },
        { id: 21, group_id: 10021, name: '西安', children: [] },
        { id: 22, group_id: 10022, name: '香港', children: [] },
      ],
    },
  ], [
    { id: 117, product_group_id: 10020, cpu_memory_display: '2 vCPU 2G' },
    { id: 118, product_group_id: 10021, cpu_memory_display: '4 vCPU 4G' },
    { id: 119, product_group_id: 10022, cpu_memory_display: '8 vCPU 8G' },
  ])

  const lightCloud = tree[0].children[0]
  assert.deepEqual(lightCloud.children.map((item) => item.label), ['美国', '西安', '香港'])
}

testKeepsSecondAndThirdCategoryLevelsBeforeProductNodes()
testDropsEmptyCategoryBranches()
testMatchesProductsByPublicGroupIdFromCategoryResource()

console.log('addServiceProductTree tests passed')
