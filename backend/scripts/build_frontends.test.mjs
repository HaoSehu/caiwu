import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '..', '..');
const buildScript = path.join(scriptDirectory, 'build_frontends.mjs');

function dryRun(environment) {
  return spawnSync(process.execPath, [buildScript, '--dry-run'], {
    cwd: repositoryRoot,
    env: { ...process.env, ...environment },
    encoding: 'utf8',
  });
}

function fourOrigins(protocol = 'https') {
  return {
    APP_URL: `${protocol}://api.example.test`,
    FRONTEND_URL: `${protocol}://www.example.test`,
    CLIENT_CONSOLE_URL: `${protocol}://console.example.test`,
    ADMIN_URL: `${protocol}://admin.example.test`,
  };
}

for (const protocol of ['http', 'https']) {
  const result = dryRun(fourOrigins(protocol));

  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, new RegExp(`API base: ${protocol}://api\\.example\\.test/api`));
}

const invalidPath = dryRun({
  ...fourOrigins(),
  APP_URL: 'https://api.example.test/api',
});
assert.notEqual(invalidPath.status, 0);
assert.match(invalidPath.stderr, /APP_URL/);

const duplicateOrigin = dryRun({
  ...fourOrigins('http'),
  CLIENT_CONSOLE_URL: 'http://www.example.test',
});
assert.notEqual(duplicateOrigin.status, 0);
assert.match(duplicateOrigin.stderr, /四个不同的 origin/);

const mixedProtocol = dryRun({
  ...fourOrigins('http'),
  APP_URL: 'https://api.example.test',
});
assert.notEqual(mixedProtocol.status, 0);
assert.match(mixedProtocol.stderr, /同一协议/);

console.log('frontend build configuration tests passed');
