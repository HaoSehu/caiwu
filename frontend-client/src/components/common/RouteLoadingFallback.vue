<template>
  <div class="route-loading-shell" role="status" aria-live="polite" aria-busy="true">
    <div class="route-loading-shell__hero">
      <span class="route-loading-shell__pill"></span>
      <span class="route-loading-shell__headline"></span>
      <span class="route-loading-shell__subline route-loading-shell__subline--wide"></span>
      <span class="route-loading-shell__subline"></span>
    </div>

    <div class="route-loading-shell__grid">
      <div v-for="index in 3" :key="index" class="route-loading-shell__card">
        <span class="route-loading-shell__line route-loading-shell__line--title"></span>
        <span class="route-loading-shell__line"></span>
        <span class="route-loading-shell__line route-loading-shell__line--short"></span>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.route-loading-shell {
  min-height: min(68vh, 720px);
  padding: 24px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.82)),
    radial-gradient(circle at top right, rgba($color-primary, 0.08), transparent 28%);
}

.route-loading-shell__hero,
.route-loading-shell__card {
  border: 1px solid rgba($color-primary, 0.08);
  border-radius: $lg-border-radius;
  background: rgba(255, 255, 255, 0.86);
  box-shadow: 0 18px 38px rgba(15, 23, 42, 0.05);
}

.route-loading-shell__hero {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 26px 24px;
}

.route-loading-shell__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin-top: 18px;
}

.route-loading-shell__card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 186px;
  padding: 22px 20px;
}

.route-loading-shell__pill,
.route-loading-shell__headline,
.route-loading-shell__subline,
.route-loading-shell__line {
  display: block;
  position: relative;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(148, 163, 184, 0.18);
}

.route-loading-shell__pill::after,
.route-loading-shell__headline::after,
.route-loading-shell__subline::after,
.route-loading-shell__line::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.78), transparent);
  animation: route-loading-shimmer 1.12s cubic-bezier(0.22, 1, 0.36, 1) infinite;
}

.route-loading-shell__pill {
  width: 96px;
  height: 12px;
}

.route-loading-shell__headline {
  width: min(360px, 72%);
  height: 28px;
}

.route-loading-shell__subline {
  width: 44%;
  height: 14px;
}

.route-loading-shell__subline--wide {
  width: min(540px, 88%);
}

.route-loading-shell__line {
  width: 100%;
  height: 14px;
}

.route-loading-shell__line--title {
  width: 54%;
  height: 20px;
}

.route-loading-shell__line--short {
  width: 72%;
}

@keyframes route-loading-shimmer {
  100% {
    transform: translateX(100%);
  }
}

@media (max-width: 960px) {
  .route-loading-shell {
    min-height: 52vh;
    padding: 18px 16px;
  }

  .route-loading-shell__hero {
    gap: 12px;
    padding: 22px 18px;
  }

  .route-loading-shell__grid {
    grid-template-columns: 1fr;
    gap: 14px;
  }

  .route-loading-shell__card {
    min-height: 148px;
    padding: 18px 16px;
  }

  .route-loading-shell__headline,
  .route-loading-shell__subline,
  .route-loading-shell__subline--wide,
  .route-loading-shell__line--title,
  .route-loading-shell__line--short {
    width: 100%;
  }
}
</style>
