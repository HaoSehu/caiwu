<template>
  <div class="mrange">
    <div class="mrange-head">
      <span class="mrange-label">{{ label }}</span>
      <div class="mrange-stepper">
        <button
          type="button"
          class="mrange-btn"
          aria-label="减少"
          :disabled="isFixed || modelValue <= min"
          @click="step(-1)"
        >
          <span class="mrange-btn-icon">
            <svg
              viewBox="0 0 12 12"
              fill="none"
              width="12"
              height="12"
              aria-hidden="true"
            >
              <path
                d="M2.5 6h7"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
              />
            </svg>
          </span>
        </button>
        <span class="mrange-value">{{ modelValue }}</span>
        <button
          type="button"
          class="mrange-btn"
          aria-label="增加"
          :disabled="isFixed || modelValue >= max"
          @click="step(1)"
        >
          <span class="mrange-btn-icon">
            <svg
              viewBox="0 0 12 12"
              fill="none"
              width="12"
              height="12"
              aria-hidden="true"
            >
              <path
                d="M6 2.5v7M2.5 6h7"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
              />
            </svg>
          </span>
        </button>
      </div>
    </div>
    <div class="mrange-track-wrap">
      <input
        type="range"
        class="mrange-slider"
        :class="{ 'is-disabled': isFixed }"
        :aria-label="label"
        :min="sliderMin"
        :max="sliderMax"
        :step="stepSize"
        :value="modelValue"
        :disabled="isFixed"
        @input="handleInput"
      />
      <div class="mrange-labels">
        <span>{{ min }}</span>
        <span>{{ max }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount } from "vue";

const props = defineProps({
  modelValue: { type: Number, default: 1 },
  label: { type: String, default: "" },
  min: { type: Number, default: 1 },
  max: { type: Number, default: 9999 },
  stepSize: { type: Number, default: 1 },
});

const emit = defineEmits(["update:modelValue"]);

const isFixed = computed(() => props.min === props.max);
const sliderMin = computed(() => props.min);
const sliderMax = computed(() => (isFixed.value ? props.min + 1 : props.max));

// 拖动滑块时约 100ms 节流，避免每个 input 都触发父组件更新
let inputTimer = null;
let pendingValue = null;

function handleInput(event) {
  pendingValue = Number(event.target.value);
  if (inputTimer) return;
  inputTimer = setTimeout(() => {
    inputTimer = null;
    if (pendingValue === null) return;
    emit("update:modelValue", pendingValue);
    pendingValue = null;
  }, 100);
}

onBeforeUnmount(() => {
  if (inputTimer) {
    clearTimeout(inputTimer);
    inputTimer = null;
    // 卸载前补发最后一次值，保证不丢失
    if (pendingValue !== null) {
      emit("update:modelValue", pendingValue);
      pendingValue = null;
    }
  }
});

function step(delta) {
  const next = props.modelValue + delta * props.stepSize;
  const clamped = Math.max(props.min, Math.min(props.max, next));
  emit("update:modelValue", clamped);
}
</script>

<style scoped lang="scss">
.mrange {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.mrange-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.mrange-label {
  font-size: 13px;
  color: $text-color-primary;
  font-weight: 500;
}

.mrange-stepper {
  display: flex;
  align-items: center;
  border: 1px solid $color-primary-border;
  border-radius: 8px;
  overflow: hidden;
  background: $bg-color-card;
}

.mrange-btn {
  // 触控目标不小于 44px；内层图标用 .mrange-btn-icon 维持原 12px 视觉尺寸
  min-width: 44px;
  min-height: 44px;
  border: none;
  background: transparent;
  color: $text-color-placeholder;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s;

  &:hover:not(:disabled) {
    background: $color-primary-soft;
    color: $color-primary;
  }

  &:disabled {
    opacity: 0.3;
    cursor: not-allowed;
  }
}

.mrange-btn-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 12px;
  height: 12px;
}

.mrange-value {
  min-width: 36px;
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
  border-left: 1px solid $color-primary-border;
  border-right: 1px solid $color-primary-border;
  padding: 0 4px;
}

.mrange-track-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mrange-slider {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 4px;
  background: $border-color;
  border-radius: 2px;
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid $color-primary;
    outline-offset: 4px;
  }

  &::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: $color-primary;
    border: 2px solid $bg-color-card;
    box-shadow: 0 1px 4px $color-primary-a30;
    cursor: pointer;
    transition: transform 0.15s;

    &:hover {
      transform: scale(1.15);
    }
  }

  &::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: $color-primary;
    border: 2px solid $bg-color-card;
    box-shadow: 0 1px 4px $color-primary-a30;
    cursor: pointer;
  }

  &.is-disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
}

.mrange-labels {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: $text-color-placeholder;
}
</style>
