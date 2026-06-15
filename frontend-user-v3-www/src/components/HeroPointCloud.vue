<template>
  <div ref="anchorRef" class="hero-point-cloud-anchor" aria-hidden="true">
    <canvas
      ref="canvasRef"
      class="hero-point-cloud-canvas"
      :class="{ 'hero-point-cloud-canvas--hidden': !isCanvasVisible }"
      aria-hidden="true"
    ></canvas>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue'
import { createRafThrottle } from '@/composables/useRafThrottle'

let THREE = null
let threeModulePromise = null
let shapeDataModule = null
let shapeDataPromise = null

async function ensureThreeModule() {
  if (THREE) {
    return THREE
  }

  if (!threeModulePromise) {
    threeModulePromise = import('three')
  }

  THREE = await threeModulePromise
  return THREE
}

async function ensureShapeDataModule() {
  if (shapeDataModule) {
    return shapeDataModule
  }

  if (!shapeDataPromise) {
    shapeDataPromise = import('@/data/heroPointCloudShapes.generated')
  }

  shapeDataModule = await shapeDataPromise
  return shapeDataModule
}

const props = defineProps({
  shape: {
    type: String,
    default: 'computer'
  },
  initialShape: {
    type: String,
    default: ''
  }
})

const canvasRef = ref(null)
const anchorRef = ref(null)
const isCanvasVisible = ref(true)
let cleanupCanvas = null

let scene, camera, renderer, particles, particleMaterial, mainGroup
let currentShape = props.shape
let renderedShape = props.shape
let pendingShape = props.shape
let particleCount = 0
let transitionStartTime = 0
let isTransitioning = false
let sourcePositions = null
let targetPositions = null
let scatterPositions = null
let transitionPhases = null
let shapeSwitchToken = 0
let animationFrameId = null
let lastRenderTime = 0
let intersectionObserver = null
let resizeObserver = null
let isDocumentVisible = true
let isComponentVisible = true
let baseParticlePositions = null
let raycaster = null
let interactionPlane = null
let pointerNdc = null
let pointerWorldPosition = null
let pointerLocalTarget = null
let pointerLocalCurrent = null
let cameraWorldDirection = null
let mainGroupWorldPosition = null
let pointerInfluence = 0
let hadPointerDisplacement = false
let isPointerInside = false
let previousInfluencedIndices = []
let candidateInfluencedIndices = []
let influencedParticleMarks = null
let influencedParticleMarkToken = 0
let particleSpatialBuckets = null
let isParticleSpatialIndexDirty = true
const seededValueCache = {}
let cachedTransitionPhases = null
const idleTaskHandles = []
const transitionDuration = 1460
const transitionStaggerRatio = 0.16
const scatterPhaseRatio = 0.44
const scatterHoldRatio = 0.16
const gatherDelayFactor = 0.42
const floatAmplitude = 0.1
const floatSpeed = 0.32
const heroParticleColor = 0x7be8ff
const cameraDistance = 5.8
const cameraFov = 75
const anchorFillRatio = 0.78
const minShapeDisplayScale = 0.18
const maxShapeDisplayScale = 0.9
const targetFrameInterval = 1000 / 45
const pointerRepelRadius = 1.52
const pointerRepelStrength = 0.92
const pointerRepelDepth = 0.38
const pointerInfluenceEase = 0.12
const pointerPositionEase = 0.16
const pointerBucketSize = pointerRepelRadius * 0.76
let pointCloudQuantizationFactor = 1

let shapeDisplayScale = 0.4
let scatterScaleCompensator = 1 / shapeDisplayScale
let mainGroupBaseY = 0
// 锚点 rect 缓存，仅在 scroll / resize / ResizeObserver 触发时失效，避免每帧 layout 抖动
let cachedAnchorRect = null
let isAnchorRectDirty = true
let lastAppliedShapeScale = shapeDisplayScale

const shapePositionCache = {}

async function ensureShapeDataLoaded() {
  if (particleCount > 0) {
    return ensureShapeDataModule()
  }

  const module = await ensureShapeDataModule()
  particleCount = Number(module.HERO_POINT_CLOUD_PARTICLE_COUNT || 0)
  pointCloudQuantizationFactor = 1 / Number(module.HERO_POINT_CLOUD_QUANTIZATION_SCALE || 1)
  return module
}

async function preloadOtherShapes() {
  const { HERO_POINT_CLOUD_SHAPES } = await ensureShapeDataLoaded()

  Object.keys(HERO_POINT_CLOUD_SHAPES).forEach((shape) => {
    if (shape !== renderedShape) {
      scheduleIdleTask(() => {
        void loadShapePositions(shape)
      }, 1200)
    }
  })
}

function scheduleIdleTask(task, timeout = 1200) {
  if (typeof window !== 'undefined' && 'requestIdleCallback' in window) {
    const handle = window.requestIdleCallback(task, { timeout })
    idleTaskHandles.push({ type: 'idle', handle })
    return
  }

  const handle = window.setTimeout(task, Math.min(timeout, 480))
  idleTaskHandles.push({ type: 'timeout', handle })
}

function clearIdleTasks() {
  if (typeof window === 'undefined') {
    idleTaskHandles.length = 0
    return
  }

  idleTaskHandles.splice(0).forEach(({ type, handle }) => {
    if (type === 'idle' && 'cancelIdleCallback' in window) {
      window.cancelIdleCallback(handle)
      return
    }

    window.clearTimeout(handle)
  })
}

async function loadShapePositions(shape) {
  const { HERO_POINT_CLOUD_SHAPES } = await ensureShapeDataLoaded()

  if (shapePositionCache[shape]) {
    return shapePositionCache[shape]
  }

  const encodedPositions = HERO_POINT_CLOUD_SHAPES[shape]
  if (!encodedPositions) {
    const fallback = new Float32Array(particleCount * 3)
    shapePositionCache[shape] = fallback
    return fallback
  }

  try {
    const positions = new Float32Array(particleCount * 3)
    const binary = window.atob(encodedPositions)

    for (let i = 0; i < positions.length; i++) {
      const byteOffset = i * 2
      let quantized = binary.charCodeAt(byteOffset) | (binary.charCodeAt(byteOffset + 1) << 8)

      if (quantized > 32767) {
        quantized -= 65536
      }

      positions[i] = quantized * pointCloudQuantizationFactor
    }

    shapePositionCache[shape] = positions
    return positions
  } catch (e) {
    console.warn('Failed to decode shape positions:', shape, e)
    const fallback = new Float32Array(particleCount * 3)
    shapePositionCache[shape] = fallback
    return fallback
  }
}

function clamp01(value) {
  return Math.max(0, Math.min(value, 1))
}

function seededUnit(index) {
  const value = Math.sin((index + 1) * 127.1) * 43758.5453123
  return value - Math.floor(value)
}

function getSeededValues(offset) {
  const cacheKey = String(offset)
  const cachedValues = seededValueCache[cacheKey]

  if (cachedValues && cachedValues.length === particleCount) {
    return cachedValues
  }

  const values = new Float32Array(particleCount)

  for (let i = 0; i < particleCount; i++) {
    values[i] = seededUnit(i + offset)
  }

  seededValueCache[cacheKey] = values
  return values
}

function createTransitionPhases(count) {
  if (cachedTransitionPhases && cachedTransitionPhases.length === count * 2) {
    return cachedTransitionPhases
  }

  const phases = new Float32Array(count * 2)
  const scatterSeeds = getSeededValues(0)
  const gatherSeeds = getSeededValues(211)

  for (let i = 0; i < count; i++) {
    phases[i * 2] = scatterSeeds[i] * transitionStaggerRatio
    phases[i * 2 + 1] = gatherSeeds[i] * transitionStaggerRatio * gatherDelayFactor
  }

  cachedTransitionPhases = phases
  return phases
}

function generateScatterPositions(basePositions, nextPositions) {
  const positions = new Float32Array(basePositions.length)
  const directionSeedX = getSeededValues(11)
  const directionSeedY = getSeededValues(29)
  const directionSeedZ = getSeededValues(53)
  const fallbackAngleSeeds = getSeededValues(71)
  const fallbackDepthSeeds = getSeededValues(89)
  const tangentSeeds = getSeededValues(173)
  const scatterDistanceSeeds = getSeededValues(17)
  const orbitSeeds = getSeededValues(101)
  const driftSeeds = getSeededValues(131)

  for (let i = 0; i < particleCount; i++) {
    const idx = i * 3
    const baseX = basePositions[idx]
    const baseY = basePositions[idx + 1]
    const baseZ = basePositions[idx + 2]
    const targetX = nextPositions[idx]
    const targetY = nextPositions[idx + 1]
    const targetZ = nextPositions[idx + 2]
    const deltaX = targetX - baseX
    const deltaY = targetY - baseY
    const deltaZ = targetZ - baseZ
    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY + deltaZ * deltaZ)

    let directionX = baseX * 0.96 + (directionSeedX[i] - 0.5) * 1.2
    let directionY = baseY * 0.96 + (directionSeedY[i] - 0.5) * 1.2
    let directionZ = baseZ * 0.72 + (directionSeedZ[i] - 0.5) * 0.62
    let directionLength = Math.sqrt(directionX * directionX + directionY * directionY + directionZ * directionZ)

    if (directionLength < 0.0001) {
      const angle = fallbackAngleSeeds[i] * Math.PI * 2
      directionX = Math.cos(angle)
      directionY = Math.sin(angle)
      directionZ = (fallbackDepthSeeds[i] - 0.5) * 0.46
      directionLength = Math.sqrt(directionX * directionX + directionY * directionY + directionZ * directionZ) || 1
    }

    const outwardX = directionX / directionLength
    const outwardY = directionY / directionLength
    const outwardZ = directionZ / directionLength
    const tangentX = -outwardY
    const tangentY = outwardX
    const tangentZ = (tangentSeeds[i] - 0.5) * 0.28
    // scatter 在 local 空间里被放大 scatterScaleCompensator 倍，
    // 与 mainGroup.scale（= shapeDisplayScale）相抵消后，世界空间里散开距离保持原幅度，
    // 让粒子能自然飞到屏幕边缘而不是被缩小的形状比例拖住。
    const scatterDistance = (2.45 + scatterDistanceSeeds[i] * 1.9 + Math.min(distance * 0.34, 1.45)) * scatterScaleCompensator
    const orbitStrength = (0.42 + orbitSeeds[i] * 0.88) * scatterScaleCompensator
    const driftToTarget = 0.04 + driftSeeds[i] * 0.04

    positions[idx] = baseX + outwardX * scatterDistance + tangentX * orbitStrength + deltaX * driftToTarget
    positions[idx + 1] = baseY + outwardY * scatterDistance + tangentY * orbitStrength + deltaY * driftToTarget
    positions[idx + 2] = baseZ + outwardZ * (scatterDistance * 0.42) + tangentZ + deltaZ * driftToTarget
  }

  return positions
}

function createParticleSprite() {
  const size = 128
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size

  const context = canvas.getContext('2d')
  const gradient = context.createRadialGradient(
    size * 0.42,
    size * 0.38,
    size * 0.02,
    size * 0.5,
    size * 0.5,
    size * 0.5
  )

  gradient.addColorStop(0, 'rgba(255,255,255,1)')
  gradient.addColorStop(0.22, 'rgba(220,250,255,1)')
  gradient.addColorStop(0.46, 'rgba(150,230,255,0.7)')
  gradient.addColorStop(0.7, 'rgba(80,180,255,0.28)')
  gradient.addColorStop(0.9, 'rgba(28,96,176,0.06)')
  gradient.addColorStop(1, 'rgba(0,0,0,0)')

  context.fillStyle = gradient
  context.fillRect(0, 0, size, size)

  const texture = new THREE.CanvasTexture(canvas)
  texture.needsUpdate = true
  return texture
}

function measureRenderSize() {
  if (typeof window === 'undefined') {
    return { width: 1280, height: 720 }
  }
  const width = Math.max(1, Math.round(window.innerWidth))
  const height = Math.max(1, Math.round(window.innerHeight))
  return { width, height }
}

function getVisibleWorldHeightAtOrigin() {
  return 2 * Math.tan((cameraFov * Math.PI) / 360) * cameraDistance
}

function markAnchorRectDirty() {
  isAnchorRectDirty = true
}

function refreshAnchorRectIfNeeded() {
  if (!isAnchorRectDirty && cachedAnchorRect) {
    return cachedAnchorRect
  }
  if (!anchorRef.value) {
    return cachedAnchorRect
  }
  cachedAnchorRect = anchorRef.value.getBoundingClientRect()
  isAnchorRectDirty = false
  return cachedAnchorRect
}

function computeShapeDisplayScaleFromRect(rect) {
  if (typeof window === 'undefined' || !rect || !rect.height || rect.height < 10) {
    return shapeDisplayScale
  }
  const viewportHeight = Math.max(window.innerHeight, 1)
  const visibleWorldHeight = getVisibleWorldHeightAtOrigin()
  // Base shape spans ≈ 5.72 units in local Y (±2.86); match it to rect.height × anchorFillRatio on screen.
  const targetWorldHeight = (rect.height * anchorFillRatio / viewportHeight) * visibleWorldHeight
  const rawScale = targetWorldHeight / 5.72
  return Math.max(minShapeDisplayScale, Math.min(maxShapeDisplayScale, rawScale))
}

function syncMainGroupAnchor() {
  if (!mainGroup || typeof window === 'undefined') {
    return
  }
  const rect = refreshAnchorRectIfNeeded()
  const viewportWidth = Math.max(window.innerWidth, 1)
  const viewportHeight = Math.max(window.innerHeight, 1)
  const visibleWorldHeight = getVisibleWorldHeightAtOrigin()
  const pxPerUnit = viewportHeight / visibleWorldHeight

  let worldX = 0
  let worldY = 0
  if (rect && rect.width && rect.height) {
    const anchorCenterX = rect.left + rect.width / 2
    const anchorCenterY = rect.top + rect.height / 2
    worldX = (anchorCenterX - viewportWidth / 2) / pxPerUnit
    worldY = (viewportHeight / 2 - anchorCenterY) / pxPerUnit
  }

  mainGroup.position.x = worldX
  mainGroupBaseY = worldY

  const nextScale = computeShapeDisplayScaleFromRect(rect)
  if (Math.abs(nextScale - shapeDisplayScale) > 0.001) {
    shapeDisplayScale = nextScale
    scatterScaleCompensator = 1 / shapeDisplayScale
  }
  // 仅当实际 scale 变化足够时才写 three.js 对象，避免每帧无意义的属性赋值和矩阵重算
  if (Math.abs(shapeDisplayScale - lastAppliedShapeScale) > 0.0005) {
    mainGroup.scale.setScalar(shapeDisplayScale)
    lastAppliedShapeScale = shapeDisplayScale
  }
}

function easeInOutCubic(t) {
  return t < 0.5
    ? 4 * t * t * t
    : 1 - Math.pow(-2 * t + 2, 3) / 2
}

function ensureBaseParticlePositions(length) {
  if (!baseParticlePositions || baseParticlePositions.length !== length) {
    baseParticlePositions = new Float32Array(length)
  }

  return baseParticlePositions
}

function markParticleSpatialIndexDirty() {
  isParticleSpatialIndexDirty = true
  particleSpatialBuckets = null
}

function restoreInfluencedParticles(positions) {
  if (!positions || !baseParticlePositions || !previousInfluencedIndices.length) {
    return false
  }

  for (let i = 0; i < previousInfluencedIndices.length; i++) {
    const particleIndex = previousInfluencedIndices[i]
    const idx = particleIndex * 3
    positions[idx] = baseParticlePositions[idx]
    positions[idx + 1] = baseParticlePositions[idx + 1]
    positions[idx + 2] = baseParticlePositions[idx + 2]
  }

  previousInfluencedIndices.length = 0
  return true
}

function ensureParticleSpatialIndex() {
  if (!baseParticlePositions || isTransitioning) {
    return false
  }

  if (!isParticleSpatialIndexDirty && particleSpatialBuckets) {
    return true
  }

  particleSpatialBuckets = new Map()

  for (let i = 0; i < particleCount; i++) {
    const idx = i * 3
    const bucketX = Math.floor(baseParticlePositions[idx] / pointerBucketSize)
    const bucketY = Math.floor(baseParticlePositions[idx + 1] / pointerBucketSize)
    const bucketKey = `${bucketX}:${bucketY}`
    const bucket = particleSpatialBuckets.get(bucketKey)

    if (bucket) {
      bucket.push(i)
      continue
    }

    particleSpatialBuckets.set(bucketKey, [i])
  }

  if (!influencedParticleMarks || influencedParticleMarks.length !== particleCount) {
    influencedParticleMarks = new Uint32Array(particleCount)
    influencedParticleMarkToken = 0
  }

  isParticleSpatialIndexDirty = false
  return true
}

function collectCandidateParticles(pointerX, pointerY) {
  if (!ensureParticleSpatialIndex()) {
    return []
  }

  candidateInfluencedIndices.length = 0
  influencedParticleMarkToken += 1

  if (influencedParticleMarkToken >= 0xffffffff) {
    influencedParticleMarks.fill(0)
    influencedParticleMarkToken = 1
  }

  const minBucketX = Math.floor((pointerX - pointerRepelRadius) / pointerBucketSize)
  const maxBucketX = Math.floor((pointerX + pointerRepelRadius) / pointerBucketSize)
  const minBucketY = Math.floor((pointerY - pointerRepelRadius) / pointerBucketSize)
  const maxBucketY = Math.floor((pointerY + pointerRepelRadius) / pointerBucketSize)

  for (let bucketX = minBucketX; bucketX <= maxBucketX; bucketX++) {
    for (let bucketY = minBucketY; bucketY <= maxBucketY; bucketY++) {
      const bucket = particleSpatialBuckets.get(`${bucketX}:${bucketY}`)

      if (!bucket) {
        continue
      }

      for (let i = 0; i < bucket.length; i++) {
        const particleIndex = bucket[i]

        if (influencedParticleMarks[particleIndex] === influencedParticleMarkToken) {
          continue
        }

        influencedParticleMarks[particleIndex] = influencedParticleMarkToken
        candidateInfluencedIndices.push(particleIndex)
      }
    }
  }

  return candidateInfluencedIndices
}

function updatePointerNdcFromEvent(event) {
  if (!pointerNdc || typeof window === 'undefined') {
    return false
  }

  const viewportWidth = Math.max(window.innerWidth, 1)
  const viewportHeight = Math.max(window.innerHeight, 1)

  pointerNdc.x = (event.clientX / viewportWidth) * 2 - 1
  pointerNdc.y = -(event.clientY / viewportHeight) * 2 + 1
  return true
}

function refreshPointerLocalTarget() {
  if (
    !isPointerInside
    || !raycaster
    || !interactionPlane
    || !pointerNdc
    || !pointerWorldPosition
    || !pointerLocalTarget
    || !pointerLocalCurrent
    || !cameraWorldDirection
    || !mainGroupWorldPosition
    || !camera
    || !mainGroup
  ) {
    return false
  }

  camera.getWorldDirection(cameraWorldDirection)
  mainGroup.getWorldPosition(mainGroupWorldPosition)
  interactionPlane.setFromNormalAndCoplanarPoint(cameraWorldDirection, mainGroupWorldPosition)
  raycaster.setFromCamera(pointerNdc, camera)

  const intersection = raycaster.ray.intersectPlane(interactionPlane, pointerWorldPosition)
  if (!intersection) {
    return false
  }

  pointerLocalTarget.copy(pointerWorldPosition)
  mainGroup.worldToLocal(pointerLocalTarget)
  return true
}

function applyPointerRepulsion() {
  const positionAttribute = particles?.geometry?.attributes?.position
  const positions = positionAttribute?.array

  if (!positions || !baseParticlePositions) {
    return
  }

  const hasPointerTarget = refreshPointerLocalTarget()
  const targetInfluence = hasPointerTarget ? 1 : 0
  pointerInfluence += (targetInfluence - pointerInfluence) * pointerInfluenceEase

  if (hasPointerTarget) {
    if (pointerInfluence < 0.02) {
      pointerLocalCurrent.copy(pointerLocalTarget)
    } else {
      pointerLocalCurrent.lerp(pointerLocalTarget, pointerPositionEase)
    }
  }

  if (pointerInfluence < 0.003) {
    const didRestoreSubset = restoreInfluencedParticles(positions)

    if (didRestoreSubset || hadPointerDisplacement || isTransitioning) {
      if (!didRestoreSubset || isTransitioning) {
        positions.set(baseParticlePositions)
      }

      positionAttribute.needsUpdate = true
      hadPointerDisplacement = false
    }
    return
  }

  const radiusSquared = pointerRepelRadius * pointerRepelRadius
  const fallbackAngleSeeds = getSeededValues(307)
  const fallbackDepthSeeds = getSeededValues(401)

  if (!isTransitioning && ensureParticleSpatialIndex()) {
    restoreInfluencedParticles(positions)

    const candidateParticles = collectCandidateParticles(pointerLocalCurrent.x, pointerLocalCurrent.y)

    for (let i = 0; i < candidateParticles.length; i++) {
      const particleIndex = candidateParticles[i]
      const idx = particleIndex * 3
      const baseX = baseParticlePositions[idx]
      const baseY = baseParticlePositions[idx + 1]
      const baseZ = baseParticlePositions[idx + 2]
      const deltaX = baseX - pointerLocalCurrent.x
      const deltaY = baseY - pointerLocalCurrent.y
      const deltaZ = (baseZ - pointerLocalCurrent.z) * 1.18
      const distanceSquared = deltaX * deltaX + deltaY * deltaY + deltaZ * deltaZ

      if (distanceSquared >= radiusSquared) {
        continue
      }

      const distance = Math.sqrt(Math.max(distanceSquared, 0.00004))
      let directionX = deltaX / distance
      let directionY = deltaY / distance
      let directionZ = deltaZ / distance

      if (distance < 0.06) {
        const angle = fallbackAngleSeeds[particleIndex] * Math.PI * 2
        directionX = Math.cos(angle)
        directionY = Math.sin(angle)
        directionZ = (fallbackDepthSeeds[particleIndex] - 0.5) * 0.82
        const fallbackLength = Math.hypot(directionX, directionY, directionZ) || 1
        directionX /= fallbackLength
        directionY /= fallbackLength
        directionZ /= fallbackLength
      }

      const falloff = 1 - distance / pointerRepelRadius
      const force = falloff * falloff * pointerRepelStrength * pointerInfluence

      positions[idx] = baseX + directionX * force
      positions[idx + 1] = baseY + directionY * force
      positions[idx + 2] = baseZ + directionZ * force * pointerRepelDepth + falloff * pointerInfluence * 0.05
      previousInfluencedIndices.push(particleIndex)
    }

    positionAttribute.needsUpdate = true
    hadPointerDisplacement = previousInfluencedIndices.length > 0
    return
  }

  for (let i = 0; i < particleCount; i++) {
    const idx = i * 3
    const baseX = baseParticlePositions[idx]
    const baseY = baseParticlePositions[idx + 1]
    const baseZ = baseParticlePositions[idx + 2]
    const deltaX = baseX - pointerLocalCurrent.x
    const deltaY = baseY - pointerLocalCurrent.y
    const deltaZ = (baseZ - pointerLocalCurrent.z) * 1.18
    const distanceSquared = deltaX * deltaX + deltaY * deltaY + deltaZ * deltaZ

    if (distanceSquared >= radiusSquared) {
      positions[idx] = baseX
      positions[idx + 1] = baseY
      positions[idx + 2] = baseZ
      continue
    }

    const distance = Math.sqrt(Math.max(distanceSquared, 0.00004))
    let directionX = deltaX / distance
    let directionY = deltaY / distance
    let directionZ = deltaZ / distance

    if (distance < 0.06) {
      const angle = fallbackAngleSeeds[i] * Math.PI * 2
      directionX = Math.cos(angle)
      directionY = Math.sin(angle)
      directionZ = (fallbackDepthSeeds[i] - 0.5) * 0.82
      const fallbackLength = Math.hypot(directionX, directionY, directionZ) || 1
      directionX /= fallbackLength
      directionY /= fallbackLength
      directionZ /= fallbackLength
    }

    const falloff = 1 - distance / pointerRepelRadius
    const force = falloff * falloff * pointerRepelStrength * pointerInfluence

    positions[idx] = baseX + directionX * force
    positions[idx + 1] = baseY + directionY * force
    positions[idx + 2] = baseZ + directionZ * force * pointerRepelDepth + falloff * pointerInfluence * 0.05
  }

  positionAttribute.needsUpdate = true
  hadPointerDisplacement = true
}

function updateTransition() {
  if (!isTransitioning || !sourcePositions || !targetPositions || !scatterPositions || !transitionPhases) {
    return
  }

  const elapsed = performance.now() - transitionStartTime
  const progress = Math.min(elapsed / transitionDuration, 1)
  const positions = ensureBaseParticlePositions(targetPositions.length)
  const scatterEnd = scatterPhaseRatio
  const gatherStart = Math.min(scatterEnd + scatterHoldRatio, 0.95)
  const gatherWindow = Math.max(1 - gatherStart, 0.0001)

  for (let i = 0; i < particleCount; i++) {
    const idx = i * 3
    const scatterPhaseDelay = transitionPhases[i * 2] * scatterEnd
    const gatherPhaseDelay = transitionPhases[i * 2 + 1] * gatherWindow

    if (progress < scatterEnd) {
      const localProgress = clamp01(
        (progress - scatterPhaseDelay) / Math.max(scatterEnd - scatterPhaseDelay, 0.0001)
      )
      const easedProgress = easeInOutCubic(localProgress)

      positions[idx] = sourcePositions[idx] + (scatterPositions[idx] - sourcePositions[idx]) * easedProgress
      positions[idx + 1] = sourcePositions[idx + 1] + (scatterPositions[idx + 1] - sourcePositions[idx + 1]) * easedProgress
      positions[idx + 2] = sourcePositions[idx + 2] + (scatterPositions[idx + 2] - sourcePositions[idx + 2]) * easedProgress
      continue
    }

    if (progress < gatherStart) {
      positions[idx] = scatterPositions[idx]
      positions[idx + 1] = scatterPositions[idx + 1]
      positions[idx + 2] = scatterPositions[idx + 2]
      continue
    }

    const localProgress = clamp01(
      (progress - gatherStart - gatherPhaseDelay) / Math.max(gatherWindow - gatherPhaseDelay, 0.0001)
    )
    const easedProgress = easeInOutCubic(localProgress)

    positions[idx] = scatterPositions[idx] + (targetPositions[idx] - scatterPositions[idx]) * easedProgress
    positions[idx + 1] = scatterPositions[idx + 1] + (targetPositions[idx + 1] - scatterPositions[idx + 1]) * easedProgress
    positions[idx + 2] = scatterPositions[idx + 2] + (targetPositions[idx + 2] - scatterPositions[idx + 2]) * easedProgress
  }

  if (progress >= 1) {
    positions.set(targetPositions)
    isTransitioning = false
    renderedShape = currentShape
    sourcePositions = null
    targetPositions = null
    scatterPositions = null
    transitionPhases = null
    previousInfluencedIndices.length = 0
    markParticleSpatialIndexDirty()
  }
}

function canAnimate() {
  return Boolean(renderer && scene && camera && isDocumentVisible && isComponentVisible)
}

function renderScene() {
  if (renderer && scene && camera) {
    renderer.render(scene, camera)
  }
}

function applyShapeImmediately(shape, positions) {
  if (!particles?.geometry?.attributes?.position?.array) {
    return
  }

  ensureBaseParticlePositions(positions.length).set(positions)
  particles.geometry.attributes.position.array.set(baseParticlePositions)
  particles.geometry.attributes.position.needsUpdate = true
  previousInfluencedIndices.length = 0
  hadPointerDisplacement = false
  currentShape = shape
  renderedShape = shape
  isTransitioning = false
  sourcePositions = null
  targetPositions = null
  scatterPositions = null
  transitionPhases = null
  markParticleSpatialIndexDirty()
  renderScene()
}

function stopAnimationLoop() {
  if (!animationFrameId) {
    return
  }

  cancelAnimationFrame(animationFrameId)
  animationFrameId = null
}

function startAnimationLoop() {
  if (animationFrameId || !canAnimate()) {
    return
  }

  lastRenderTime = 0
  animationFrameId = requestAnimationFrame(animate)
}

function updateAnimationState() {
  if (canAnimate()) {
    startAnimationLoop()
    return
  }

  stopAnimationLoop()
}

function animate(now = performance.now()) {
  if (!canAnimate()) {
    animationFrameId = null
    return
  }

  animationFrameId = requestAnimationFrame(animate)

  if (now - lastRenderTime < targetFrameInterval) {
    return
  }

  lastRenderTime = now
  syncMainGroupAnchor()
  updateTransition()

  const time = now * 0.001
  if (mainGroup) {
    mainGroup.position.y = mainGroupBaseY + Math.sin(time * floatSpeed) * floatAmplitude
    mainGroup.position.z = -0.06 + Math.cos(time * 0.24) * 0.04
    mainGroup.rotation.y = 0.12 + Math.sin(time * 0.2) * 0.06
    mainGroup.rotation.x = -0.08 + Math.cos(time * 0.22) * 0.03
    mainGroup.rotation.z = Math.sin(time * 0.16) * 0.018
  }
  if (particleMaterial) {
    particleMaterial.size = 0.019 + (Math.sin(time * 0.9) + 1) * 0.0008
  }

  applyPointerRepulsion()
  renderScene()
}

async function switchShape(newShape) {
  pendingShape = newShape

  if (!particles) {
    return
  }

  if (!isTransitioning && renderedShape === newShape) {
    currentShape = newShape
    return
  }

  const requestToken = ++shapeSwitchToken
  const nextPositions = await loadShapePositions(newShape)

  if (requestToken !== shapeSwitchToken || !particles?.geometry?.attributes?.position?.array) {
    return
  }

  if (!canAnimate()) {
    applyShapeImmediately(newShape, nextPositions)
    return
  }

  currentShape = newShape
  previousInfluencedIndices.length = 0
  hadPointerDisplacement = false
  sourcePositions = new Float32Array(baseParticlePositions || particles.geometry.attributes.position.array)
  targetPositions = new Float32Array(nextPositions)
  scatterPositions = generateScatterPositions(sourcePositions, targetPositions)
  transitionPhases = createTransitionPhases(particleCount)
  transitionStartTime = performance.now()
  isTransitioning = true
  markParticleSpatialIndexDirty()

  if (particleMaterial) {
    particleMaterial.color.setHex(heroParticleColor)
  }
}

defineExpose({ switchShape })

onMounted(async () => {
  if (!canvasRef.value) return

  // 等待下一帧确保 DOM 已渲染
  await new Promise(resolve => requestAnimationFrame(resolve))
  await ensureThreeModule()
  await ensureShapeDataLoaded()
  raycaster = new THREE.Raycaster()
  interactionPlane = new THREE.Plane()
  pointerNdc = new THREE.Vector2()
  pointerWorldPosition = new THREE.Vector3()
  pointerLocalTarget = new THREE.Vector3()
  pointerLocalCurrent = new THREE.Vector3()
  cameraWorldDirection = new THREE.Vector3()
  mainGroupWorldPosition = new THREE.Vector3()

  const { width, height } = measureRenderSize()

  // Scene
  scene = new THREE.Scene()
  // 透明背景
  scene.background = null

  // Camera
  camera = new THREE.PerspectiveCamera(75, width / height, 0.1, 1000)
  camera.position.z = cameraDistance

  // Renderer - 启用透明背景
  renderer = new THREE.WebGLRenderer({
    canvas: canvasRef.value,
    antialias: true,
    alpha: true,
    premultipliedAlpha: false
  })
  renderer.setSize(width, height, false)
  // DPR 压到 1.5：高分屏像素吞吐量直接减半以上，肉眼几乎看不出差别，粒子质感只要保证 ≥ 1x
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5))
  renderer.setClearColor(0x000000, 0)
  renderer.outputColorSpace = THREE.SRGBColorSpace

  // Main group
  mainGroup = new THREE.Group()
  scene.add(mainGroup)

  // Initial positions
  const mountedTargetShape = pendingShape
  const mountedInitialShape = props.initialShape && props.initialShape !== mountedTargetShape
    ? props.initialShape
    : mountedTargetShape

  currentShape = mountedInitialShape
  renderedShape = mountedInitialShape
  const initialPositions = await loadShapePositions(mountedInitialShape)
  baseParticlePositions = new Float32Array(initialPositions)

  const particleTexture = createParticleSprite()

  const particleGeometry = new THREE.BufferGeometry()
  particleGeometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(baseParticlePositions), 3))

  particleMaterial = new THREE.PointsMaterial({
    color: heroParticleColor,
    size: 0.019,
    map: particleTexture,
    transparent: true,
    opacity: 0.9,
    alphaTest: 0.04,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
    sizeAttenuation: true
  })

  particles = new THREE.Points(particleGeometry, particleMaterial)
  mainGroup.add(particles)

  // Auto slight 3D motion
  mainGroup.rotation.x = -0.08
  mainGroup.rotation.y = 0.12
  mainGroup.position.z = -0.06
  syncMainGroupAnchor()

  // Handle resize
  const handleResize = () => {
    if (!canvasRef.value || !camera || !renderer) return
    const { width: newWidth, height: newHeight } = measureRenderSize()
    camera.aspect = newWidth / newHeight
    camera.updateProjectionMatrix()
    renderer.setSize(newWidth, newHeight, false)
    markAnchorRectDirty()
    syncMainGroupAnchor()
    renderScene()
  }

  // scroll 只标脏 rect 缓存，rAF 帧内读一次，不在 scroll 回调里同步渲染（避免抖动 + 多余 render）
  const handleScroll = () => {
    markAnchorRectDirty()
  }

  const handleVisibilityChange = () => {
    isDocumentVisible = document.visibilityState === 'visible'
    updateAnimationState()
  }

  const handlePointerEnter = (event) => {
    isPointerInside = updatePointerNdcFromEvent(event)
  }

  const handlePointerMove = (event) => {
    isPointerInside = updatePointerNdcFromEvent(event)
  }

  const handlePointerLeave = () => {
    isPointerInside = false
  }

  const handleResizeWithRaf = createRafThrottle(handleResize)
  const handleScrollWithRaf = createRafThrottle(handleScroll)
  const handlePointerMoveWithRaf = createRafThrottle(handlePointerMove)

  window.addEventListener('resize', handleResizeWithRaf, { passive: true })
  window.addEventListener('scroll', handleScrollWithRaf, { passive: true, capture: true })
  document.addEventListener('visibilitychange', handleVisibilityChange)
  // 指针事件挂在锚点上（canvas 已经 pointer-events: none，不阻挡 UI 交互）
  const pointerTarget = anchorRef.value
  if (pointerTarget) {
    pointerTarget.addEventListener('pointerenter', handlePointerEnter, { passive: true })
    pointerTarget.addEventListener('pointermove', handlePointerMoveWithRaf, { passive: true })
    pointerTarget.addEventListener('pointerleave', handlePointerLeave, { passive: true })
    pointerTarget.addEventListener('pointercancel', handlePointerLeave, { passive: true })
  }

  if ('IntersectionObserver' in window && anchorRef.value) {
    intersectionObserver = new IntersectionObserver(([entry]) => {
      const nowVisible = entry?.isIntersecting ?? true
      isComponentVisible = nowVisible
      isCanvasVisible.value = nowVisible
      updateAnimationState()
    }, {
      threshold: 0.02
    })
    intersectionObserver.observe(anchorRef.value)
  } else {
    isCanvasVisible.value = true
  }

  if ('ResizeObserver' in window && anchorRef.value) {
    resizeObserver = new ResizeObserver(() => {
      markAnchorRectDirty()
      handleResizeWithRaf()
    })
    resizeObserver.observe(anchorRef.value)
  }

  // Store cleanup reference
  cleanupCanvas = () => {
    window.removeEventListener('resize', handleResizeWithRaf)
    window.removeEventListener('scroll', handleScrollWithRaf, { capture: true })
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    if (pointerTarget) {
      pointerTarget.removeEventListener('pointerenter', handlePointerEnter)
      pointerTarget.removeEventListener('pointermove', handlePointerMoveWithRaf)
      pointerTarget.removeEventListener('pointerleave', handlePointerLeave)
      pointerTarget.removeEventListener('pointercancel', handlePointerLeave)
    }
    if (intersectionObserver) {
      intersectionObserver.disconnect()
      intersectionObserver = null
    }
    if (resizeObserver) {
      resizeObserver.disconnect()
      resizeObserver = null
    }
    handleResizeWithRaf.cancel()
    handleScrollWithRaf.cancel()
    handlePointerMoveWithRaf.cancel()
  }

  void preloadOtherShapes()

  currentShape = mountedTargetShape
  pendingShape = mountedTargetShape

  if (mountedTargetShape !== mountedInitialShape) {
    void switchShape(mountedTargetShape)
  } else if (pendingShape !== renderedShape) {
    void switchShape(pendingShape)
  }

  renderScene()
  updateAnimationState()
})

onBeforeUnmount(() => {
  stopAnimationLoop()
  clearIdleTasks()
  if (cleanupCanvas) {
    cleanupCanvas()
    cleanupCanvas = null
  }
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
  if (renderer) {
    renderer.dispose()
  }
  if (particles && particles.geometry) {
    particles.geometry.dispose()
  }
  if (particleMaterial && particleMaterial.map) {
    particleMaterial.map.dispose()
  }
  baseParticlePositions = null
  raycaster = null
  interactionPlane = null
  pointerNdc = null
  pointerWorldPosition = null
  pointerLocalTarget = null
  pointerLocalCurrent = null
  cameraWorldDirection = null
  mainGroupWorldPosition = null
  pointerInfluence = 0
  hadPointerDisplacement = false
  isPointerInside = false
  previousInfluencedIndices.length = 0
  candidateInfluencedIndices.length = 0
  influencedParticleMarks = null
  influencedParticleMarkToken = 0
  particleSpatialBuckets = null
  isParticleSpatialIndexDirty = true
  cachedTransitionPhases = null
})

watch(() => props.shape, (newShape) => {
  void switchShape(newShape)
})
</script>

<style scoped lang="scss">
.hero-point-cloud-anchor {
  position: relative;
  display: block;
  width: 100% !important;
  max-width: 600px;
  margin-left: auto;
  margin-right: 0;
  aspect-ratio: 6 / 5;
  height: auto !important;
  background: transparent;
  pointer-events: auto;
}

.hero-point-cloud-canvas {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  display: block;
  background: transparent;
  pointer-events: none;
  // 压到 hero-stage 其他子节点（文字、按钮、卡片）之下，粒子只在空白区域可见
  z-index: -1;
  transition: opacity 220ms ease;
  will-change: transform;
}

.hero-point-cloud-canvas.hero-point-cloud-canvas--hidden {
  opacity: 0;
  visibility: hidden;
}
</style>
