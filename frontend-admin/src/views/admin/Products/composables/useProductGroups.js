/**
 * useProductGroups — 分组状态 + API
 * 接收 filters / page / loadProductsFn 作为依赖，内部管理分组树相关的 state、
 * computed、watch 和所有与分组相关的事件处理器。
 */
import { computed, nextTick, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import productApi from '@/api/product'
import {
  buildAssignableCategoryOptions,
  buildCategoryTreeNode,
  filterCategoryTree,
} from '../catalogUtils'

export function useProductGroups({ filters, page, loadProductsFn }) {
  // ── loading ──────────────────────────────────────────────────────────────
  const groupLoading = ref(false)
  const groupSubmitting = ref(false)

  // ── data ─────────────────────────────────────────────────────────────────
  const groupTree = ref([])
  const groupOptions = ref([])
  const groupKeyword = ref('')
  const expandedGroupKeys = ref([])
  const activeTreeNodeId = ref(0)

  // ── drag ─────────────────────────────────────────────────────────────────
  const draggingGroupId = ref(0)
  const groupDropTargetId = ref(0)
  const groupDropPosition = ref('')

  // ── dialog / form refs ───────────────────────────────────────────────────
  const groupTreeRef = ref()
  const groupFormRef = ref()
  const groupDialogVisible = ref(false)
  const editingGroup = ref(null)

  // ── computed ─────────────────────────────────────────────────────────────
  const activeGroupId = computed(() => {
    const value = Number(filters.category_id)
    return Number.isFinite(value) && value > 0 ? value : 0
  })

  const activeGroupTreeKey = computed(() =>
    activeTreeNodeId.value ? `category-${activeTreeNodeId.value}` : null,
  )

  const normalizedGroupKeyword = computed(() =>
    groupKeyword.value.trim().toLowerCase(),
  )

  const canDragGroupTree = computed(
    () => !normalizedGroupKeyword.value && !groupLoading.value,
  )

  const groupNodeMap = computed(() => {
    const map = new Map()
    const walk = (nodes) => {
      nodes.forEach((node) => {
        map.set(Number(node.id), node)
        if (Array.isArray(node.children) && node.children.length) walk(node.children)
      })
    }
    walk(groupTree.value.map((g) => buildCategoryTreeNode(g)))
    return map
  })

  const selectedGroupNode = computed(() => groupNodeMap.value.get(activeGroupId.value) || null)
  const selectedGroupLevel = computed(() => Number(selectedGroupNode.value?.level || 0))
  const isRootGroupSelected = computed(() => activeGroupId.value > 0 && selectedGroupLevel.value === 1)

  const selectedGroupLabel = computed(() => {
    const matched = groupOptions.value.find((item) => item.id === activeGroupId.value)
    return matched?.label || ''
  })

  const currentTypeRootGroupCount = computed(() => groupTree.value.length)
  const currentTypeChildGroupCount = computed(() =>
    groupTree.value.reduce(
      (total, item) => total + Number(item.children_count ?? item.children?.length ?? 0),
      0,
    ),
  )
  const groupOverviewText = computed(
    () => `${currentTypeRootGroupCount.value} 分组 / ${currentTypeChildGroupCount.value} 子菜单`,
  )

  const normalizedGroupKeywordRef = normalizedGroupKeyword
  const filteredRawGroupTree = computed(() =>
    filterCategoryTree(groupTree.value, normalizedGroupKeywordRef.value),
  )
  const groupTreeNodes = computed(() =>
    filteredRawGroupTree.value.map((g) => buildCategoryTreeNode(g)),
  )
  const assignableGroupOptions = computed(() => buildAssignableCategoryOptions(groupTree.value))

  const groupDragHint = computed(() => {
    if (!draggingGroupId.value || !groupDropTargetId.value || !groupDropPosition.value) return ''
    const target = groupNodeMap.value.get(groupDropTargetId.value)
    if (!target) return ''
    if (groupDropPosition.value === 'inner') return `将分类移入「${target.name}」作为子级`
    return `将分类插入到「${target.name}」${groupDropPosition.value === 'before' ? '前面' : '后面'}`
  })

  const availableParentGroups = computed(() =>
    groupTree.value
      .filter((item) => item.id !== editingGroup.value?.id)
      .map((item) => ({
        id: item.id,
        category_id: item.category_id ?? item.id,
        label: item.name,
        product_type: item.product_type,
      })),
  )

  // ── watches ───────────────────────────────────────────────────────────────
  watch(
    [groupTreeNodes, activeGroupTreeKey, expandedGroupKeys],
    async () => {
      await nextTick()
      syncGroupTreeExpansion()
      groupTreeRef.value?.setCurrentKey(activeGroupTreeKey.value)
    },
    { immediate: true },
  )

  watch(
    [normalizedGroupKeyword, filteredRawGroupTree],
    ([keyword, filteredGroups]) => {
      if (keyword) {
        expandedGroupKeys.value = filteredGroups.map((g) => `category-${g.id}`)
        return
      }
      if (selectedGroupLevel.value === 2 && selectedGroupNode.value?.parent_category_id) {
        expandedGroupKeys.value = [`category-${selectedGroupNode.value.parent_category_id}`]
        return
      }
      expandedGroupKeys.value = []
    },
    { immediate: true },
  )

  watch(
    activeGroupId,
    (groupId) => {
      if (!groupId) return
      activeTreeNodeId.value = groupId
      const currentNode = groupNodeMap.value.get(groupId)
      if (currentNode && Number(currentNode.level) > 1 && currentNode.parent_category_id) {
        expandedGroupKeys.value = [`category-${currentNode.parent_category_id}`]
      }
    },
    { immediate: true },
  )

  // ── private helpers ───────────────────────────────────────────────────────
  function syncGroupTreeExpansion() {
    if (!groupTreeRef.value?.getNode) return
    const expandedSet = new Set(expandedGroupKeys.value)
    groupTree.value.forEach((group) => {
      const treeNode = groupTreeRef.value.getNode(`category-${group.id}`)
      if (!treeNode) return
      if (expandedSet.has(`category-${group.id}`)) treeNode.expand?.()
      else treeNode.collapse?.()
    })
  }

  function resolveVerticalDropPosition(event) {
    const target = event.target.closest('.el-tree-node__content, .product-drop-zone, .drag-handle')
    if (!target) return 'after'
    const rect = target.getBoundingClientRect()
    const ratio = (event.clientY - rect.top) / Math.max(rect.height, 1)
    return ratio <= 0.5 ? 'before' : 'after'
  }

  function resetGroupDragState() {
    draggingGroupId.value = 0
    groupDropTargetId.value = 0
    groupDropPosition.value = ''
  }

  // ── public methods ────────────────────────────────────────────────────────
  async function loadGroups(options = {}) {
    if (!filters.product_type) {
      groupTree.value = []
      groupOptions.value = []
      return
    }
    groupLoading.value = true
    try {
      const res = await productApi.categories({ product_type: filters.product_type })
      groupTree.value = res.data.tree || []
      groupOptions.value = res.data.options || []

      if (activeGroupId.value && !groupOptions.value.some((item) => item.id === activeGroupId.value)) {
        clearGroupFilter({ preserveTreeFocus: false })
      }

      const focusedExists = groupTree.value.some(
        (group) =>
          Number(group.id) === Number(activeTreeNodeId.value) ||
          (Array.isArray(group.children) &&
            group.children.some((child) => Number(child.id) === Number(activeTreeNodeId.value))),
      )
      if (!focusedExists) activeTreeNodeId.value = 0

      if (options.autoSelectFirst && !activeGroupId.value && groupTree.value.length) {
        let firstLeaf = null
        for (const root of groupTree.value) {
          if (Array.isArray(root.children) && root.children.length) {
            firstLeaf = root.children[0]
            break
          } else if (!root.children_count || Number(root.children_count) === 0) {
            firstLeaf = root
            break
          }
        }
        if (firstLeaf) {
          activeTreeNodeId.value = Number(firstLeaf.id)
          filters.category_id = firstLeaf.category_id || ''
          page.value = 1
          if (firstLeaf.parent_category_id) {
            expandedGroupKeys.value = [`category-${firstLeaf.parent_category_id}`]
          }
        }
      }
    } finally {
      groupLoading.value = false
    }
  }

  function selectGroup(group) {
    activeTreeNodeId.value = Number(group?.id || 0)
    filters.category_id = group?.category_id || ''
    page.value = 1
    loadProductsFn()
  }

  function clearGroupFilter(options = {}) {
    filters.category_id = ''
    if (!options.preserveTreeFocus) activeTreeNodeId.value = 0
    page.value = 1
    loadProductsFn()
  }

  function clearFilter(key) {
    filters[key] = ''
    if (key === 'category_id') {
      activeTreeNodeId.value = 0
      filters.category_id = ''
    }
    page.value = 1
    loadProductsFn()
  }

  function handleGroupTreeSelect(data) {
    activeTreeNodeId.value = Number(data.id || 0)
    if (Number(data.level) === 1) { toggleRootGroup(data); return }
    if (data.parent_category_id) expandedGroupKeys.value = [`category-${data.parent_category_id}`]
    selectGroup(data)
  }

  function handleGroupNodeExpand(data) {
    if (Number(data.level) !== 1) return
    expandRootGroup(data)
  }

  function handleGroupNodeCollapse(data) {
    if (Number(data.level) !== 1) return
    collapseRootGroup(data)
  }

  function toggleRootGroup(group) {
    const groupKey = `category-${group.id}`
    expandedGroupKeys.value.includes(groupKey) ? collapseRootGroup(group) : expandRootGroup(group)
  }

  function expandRootGroup(group) {
    const groupKey = `category-${group.id}`
    const hasSelectedChild = Number(selectedGroupNode.value?.parent_category_id) === Number(group.category_id || group.id)
    if (expandedGroupKeys.value.length === 1 && expandedGroupKeys.value[0] === groupKey) return
    expandedGroupKeys.value = [groupKey]
    if (selectedGroupNode.value && !hasSelectedChild) clearGroupFilter({ preserveTreeFocus: true })
  }

  function collapseRootGroup(group) {
    const hasSelectedChild = Number(selectedGroupNode.value?.parent_category_id) === Number(group.category_id || group.id)
    if (expandedGroupKeys.value.length === 0) return
    expandedGroupKeys.value = []
    if (hasSelectedChild) clearGroupFilter({ preserveTreeFocus: true })
  }

  function allowGroupTreeDrag(node) {
    return canDragGroupTree.value && Number(node?.data?.id || 0) > 0
  }

  function allowGroupTreeDrop(draggingNode, dropNode, type) {
    if (!canDragGroupTree.value) return false
    const draggingLevel = Number(draggingNode?.data?.level || 0)
    const dropLevel = Number(dropNode?.data?.level || 0)
    if (draggingLevel === 1) return dropLevel === 1 && ['before', 'after'].includes(type)
    if (draggingLevel === 2) {
      if (dropLevel === 1) return type === 'inner'
      return dropLevel === 2 && ['before', 'after'].includes(type)
    }
    return false
  }

  function handleGroupTreeDragStart(draggingNode) {
    draggingGroupId.value = Number(draggingNode?.data?.id || 0)
    groupDropTargetId.value = 0
    groupDropPosition.value = ''
  }

  function handleGroupTreeDragOver(draggingNode, dropNode, event) {
    if (!canDragGroupTree.value) return
    const draggingData = draggingNode?.data || null
    const dropData = dropNode?.data || null
    if (!draggingData || !dropData) { resetGroupDragState(); return }
    let position = ''
    if (Number(draggingData.level) === 1 && Number(dropData.level) === 1) {
      position = resolveVerticalDropPosition(event)
    } else if (Number(draggingData.level) === 2 && Number(dropData.level) === 1) {
      position = 'inner'
    } else if (Number(draggingData.level) === 2 && Number(dropData.level) === 2) {
      position = resolveVerticalDropPosition(event)
    }
    if (!position || !allowGroupTreeDrop(draggingNode, dropNode, position)) {
      groupDropTargetId.value = 0
      groupDropPosition.value = ''
      return
    }
    draggingGroupId.value = Number(draggingData.id || 0)
    groupDropTargetId.value = Number(dropData.id || 0)
    groupDropPosition.value = position
  }

  async function handleGroupTreeDrop(draggingNode, dropNode, dropType) {
    const draggingData = draggingNode?.data || null
    const dropData = dropNode?.data || null
    if (!draggingData || !dropData) return
    const payload = {
      category_id: Number(draggingData.category_id || draggingData.id),
      target_parent_category_id: null,
      target_product_type: filters.product_type || null,
      reference_category_id: null,
      position: 'append',
    }
    if (Number(draggingData.level) === 1) {
      payload.reference_category_id = Number(dropData.category_id || dropData.id)
      payload.position = dropType === 'before' ? 'before' : 'after'
    } else if (dropType === 'inner') {
      payload.target_parent_category_id = Number(dropData.category_id || dropData.id)
      payload.position = 'append'
    } else {
      payload.target_parent_category_id = Number(dropData.parent_category_id || dropData.parent_id || 0) || null
      payload.reference_category_id = Number(dropData.category_id || dropData.id)
      payload.position = dropType === 'before' ? 'before' : 'after'
    }
    groupLoading.value = true
    try {
      await productApi.reorderCategory(payload)
      ElMessage.success('分类位置已更新')
      await Promise.all([loadGroups(), loadProductsFn()])
    } finally {
      groupLoading.value = false
      resetGroupDragState()
    }
  }

  function handleGroupTreeDragEnd() { resetGroupDragState() }

  function groupTreeNodeMainClass(data) {
    const isGroupCurrentTarget = Number(groupDropTargetId.value) === Number(data.id)
    return {
      'is-group-drop-inner': isGroupCurrentTarget && groupDropPosition.value === 'inner',
      'is-group-drop-before': isGroupCurrentTarget && groupDropPosition.value === 'before',
      'is-group-drop-after': isGroupCurrentTarget && groupDropPosition.value === 'after',
    }
  }

  function groupTreeNodeNote(node) {
    if (Number(node.level) === 1) return `${node.children_count || 0} 个子分类`
    return `${node.products_count || 0} 个商品`
  }

  async function handleDeleteGroup(group) {
    await productApi.deleteCategory(group.id)
    ElMessage.success('分组已删除')
  }

  async function handleSubmitGroup({ groupForm }) {
    groupSubmitting.value = true
    try {
      const parentCategoryId = Number(groupForm.parent_category_id || 0) || null
      const matchedParent = availableParentGroups.value.find((item) => Number(item.category_id) === Number(parentCategoryId || 0))
      const payload = {
        product_type: groupForm.product_type || null,
        parent_category_id: parentCategoryId || matchedParent?.category_id || null,
        name: groupForm.name,
        slogan: groupForm.slogan,
        sort_order: groupForm.sort_order,
        is_visible: groupForm.is_visible,
      }
      if (editingGroup.value) {
        await productApi.updateCategory(editingGroup.value.id, payload)
        ElMessage.success('分组已更新')
      } else {
        await productApi.createCategory(payload)
        ElMessage.success('分组已创建')
      }
      groupDialogVisible.value = false
    } finally {
      groupSubmitting.value = false
    }
  }

  return {
    // state
    groupLoading, groupSubmitting, groupTree, groupOptions, groupKeyword,
    expandedGroupKeys, activeTreeNodeId, draggingGroupId, groupDropTargetId,
    groupDropPosition, groupTreeRef, groupFormRef, groupDialogVisible, editingGroup,
    // computed
    activeGroupId, activeGroupTreeKey, normalizedGroupKeyword, canDragGroupTree,
    groupNodeMap, selectedGroupNode, selectedGroupLevel, isRootGroupSelected,
    selectedGroupLabel, currentTypeRootGroupCount, currentTypeChildGroupCount,
    groupOverviewText, filteredRawGroupTree, groupTreeNodes, assignableGroupOptions,
    groupDragHint, availableParentGroups,
    // methods
    loadGroups, selectGroup, clearGroupFilter, clearFilter,
    handleGroupTreeSelect, handleGroupNodeExpand, handleGroupNodeCollapse,
    allowGroupTreeDrag, allowGroupTreeDrop,
    handleGroupTreeDragStart, handleGroupTreeDragOver, handleGroupTreeDrop, handleGroupTreeDragEnd,
    groupTreeNodeMainClass, groupTreeNodeNote,
    handleDeleteGroup, handleSubmitGroup,
    resolveVerticalDropPosition,
  }
}
