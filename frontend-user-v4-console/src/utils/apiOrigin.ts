function normalizeApiBaseUrl(value: unknown): string {
  return String(value || '').trim().replace(/\/+$/, '');
}

/**
 * Returns the API deployment origin from VITE_API_BASE_URL, whose configured
 * value includes the /api path used by HTTP requests.
 */
export function resolveApiOrigin(apiBaseUrl: unknown): string {
  const normalized = normalizeApiBaseUrl(apiBaseUrl);
  if (!normalized) return '';

  try {
    const url = new URL(normalized);
    return /^https?:$/.test(url.protocol) ? url.origin : '';
  } catch {
    return '';
  }
}

/**
 * Back-end hosted uploads must be loaded from the API deployment, not from a
 * separately deployed console host. Static console assets intentionally keep
 * their original relative path.
 */
export function resolveApiManagedAssetUrl(value: unknown, apiBaseUrl: unknown): string {
  const normalized = String(value || '').trim().replace(/\\/g, '/');
  const managedPath = normalized.match(/^(?:\.\/|\/+)?((?:uploads|media)\/.+)$/i)?.[1];
  if (!managedPath) return '';

  const apiOrigin = resolveApiOrigin(apiBaseUrl);
  const absolutePath = `/${managedPath}`;
  return apiOrigin ? `${apiOrigin}${absolutePath}` : absolutePath;
}
