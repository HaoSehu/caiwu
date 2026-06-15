import { initRuntimeConnectionHints, primeConnectionHints } from '@caiwu/shared/runtime'

export function initClientRuntimeConnectionHints(options = {}) {
  initRuntimeConnectionHints(options)
}

export function primeClientConnectionHints(options = {}) {
  primeConnectionHints(options)
}
