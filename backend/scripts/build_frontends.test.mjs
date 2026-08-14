import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '..', '..');
const buildScript = path.join(scriptDirectory, 'build_frontends.mjs');

function dryRun(environment, envArgument) {
  const args = [buildScript, '--dry-run'];
  if (envArgument) {
    args.push('--env', envArgument);
  }

  return spawnSync(process.execPath, args, {
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

// --env dev 与省略等价：仍以传入的环境变量为准
const explicitDev = dryRun(fourOrigins(), 'dev');
assert.equal(explicitDev.status, 0, explicitDev.stderr);
assert.match(explicitDev.stdout, /API base: https:\/\/api\.example\.test\/api/);

// 使用非 dev 环境文件：四个公开地址必须来自所选文件
const tempDir = mkdtempSync(path.join(os.tmpdir(), 'build-frontends-'));
try {
  const prodEnvPath = path.join(tempDir, 'env.production');
  writeFileSync(
    prodEnvPath,
    [
      'APP_URL=https://api.example.prod',
      'FRONTEND_URL=https://www.example.prod',
      'CLIENT_CONSOLE_URL=https://console.example.prod',
      'ADMIN_URL=https://admin.example.prod',
      'CLIENT_SESSION_COOKIE_DOMAIN=.example.prod',
    ].join('\n'),
  );

  const fromFile = dryRun({}, prodEnvPath);
  assert.equal(fromFile.status, 0, fromFile.stderr);
  assert.match(fromFile.stdout, /环境文件: .*env\.production/);
  assert.match(fromFile.stdout, /API base: https:\/\/api\.example\.prod\/api/);

  // 生产环境缺键时报错，且不得静默回退到本地 backend/.env
  writeFileSync(
    prodEnvPath,
    [
      'APP_URL=https://api.example.prod',
      'FRONTEND_URL=https://www.example.prod',
      'CLIENT_CONSOLE_URL=https://console.example.prod',
      // 缺少 ADMIN_URL
    ].join('\n'),
  );
  const missing = dryRun({}, prodEnvPath);
  assert.notEqual(missing.status, 0);
  assert.match(missing.stderr, /ADMIN_URL/);
  assert.match(missing.stderr, /不得回退/);

  // 环境变量可显式补齐缺失键，优先级最高
  const padded = dryRun({ ADMIN_URL: 'https://admin.example.prod' }, prodEnvPath);
  assert.equal(padded.status, 0, padded.stderr);
} finally {
  rmSync(tempDir, { recursive: true, force: true });
}

console.log('frontend build configuration tests passed');
