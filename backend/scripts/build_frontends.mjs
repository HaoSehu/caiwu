import { spawn } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '..', '..');
const backendEnvPath = path.join(repositoryRoot, 'backend', '.env');
const npmCommand = process.platform === 'win32' ? 'npm.cmd' : 'npm';

const applications = {
  admin: {
    workspace: 'chuangouyun-admin-v3',
    assetVariable: 'VITE_ADMIN_ASSET_BASE_URL',
  },
  www: {
    workspace: 'chuangouyun-user-v3-www',
    assetVariable: 'VITE_WWW_ASSET_BASE_URL',
  },
  console: {
    workspace: 'chuangouyun-user-v4-console',
    assetVariable: 'VITE_CONSOLE_ASSET_BASE_URL',
  },
};

function readEnvFile(filePath) {
  if (!existsSync(filePath)) {
    return {};
  }

  return readFileSync(filePath, 'utf8')
    .split(/\r?\n/)
    .reduce((env, line) => {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('#')) {
        return env;
      }

      const separator = trimmed.indexOf('=');
      if (separator <= 0) {
        return env;
      }

      const key = trimmed.slice(0, separator).trim();
      const rawValue = trimmed.slice(separator + 1).trim();
      const value = rawValue.length >= 2
        && ((rawValue.startsWith('"') && rawValue.endsWith('"')) || (rawValue.startsWith("'") && rawValue.endsWith("'")))
        ? rawValue.slice(1, -1)
        : rawValue;

      env[key] = value;
      return env;
    }, {});
}

function parseArguments(argv) {
  const options = { dryRun: false, target: 'all', env: 'dev' };

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--dry-run') {
      options.dryRun = true;
      continue;
    }

    if (argument === '--target') {
      options.target = argv[index + 1] || '';
      index += 1;
      continue;
    }

    if (argument.startsWith('--target=')) {
      options.target = argument.slice('--target='.length);
      continue;
    }

    if (argument === '--env') {
      options.env = argv[index + 1] || '';
      index += 1;
      continue;
    }

    if (argument.startsWith('--env=')) {
      options.env = argument.slice('--env='.length);
      continue;
    }

    throw new Error(`不支持的参数：${argument}`);
  }

  if (options.target !== 'all' && !Object.hasOwn(applications, options.target)) {
    throw new Error('--target 只能是 all、admin、www 或 console。');
  }

  return options;
}

// --env 解析：dev（默认，backend/.env）、production（backend/.env.production）、或指向 env 文件的相对/绝对路径。
function resolveEnvPath(env, repositoryRoot, backendEnvPath) {
  if (!env || env === 'dev') {
    return backendEnvPath;
  }

  if (env === 'production') {
    return path.join(repositoryRoot, 'backend', '.env.production');
  }

  return path.resolve(repositoryRoot, env);
}

function normalizeOrigin(label, rawValue) {
  let url;
  try {
    url = new URL(String(rawValue || '').trim());
  } catch {
    throw new Error(`${label} 必须是 HTTP(S) 根地址。`);
  }

  if (!['http:', 'https:'].includes(url.protocol)
    || !url.hostname
    || url.username
    || url.password
    || url.pathname !== '/'
    || url.search
    || url.hash) {
    throw new Error(`${label} 必须是无路径、无账号信息的 HTTP(S) 根地址。`);
  }

  return url.origin;
}

function runNpm(args, env) {
  return new Promise((resolve, reject) => {
    const command = process.platform === 'win32' ? process.env.ComSpec || 'cmd.exe' : npmCommand;
    const commandArgs = process.platform === 'win32'
      ? ['/d', '/s', '/c', [npmCommand, ...args].join(' ')]
      : args;
    const child = spawn(command, commandArgs, {
      cwd: repositoryRoot,
      env,
      stdio: 'inherit',
      shell: false,
    });

    child.once('error', reject);
    child.once('exit', (code, signal) => {
      if (code === 0) {
        resolve();
        return;
      }

      reject(new Error(`前端构建失败（${signal ? `signal ${signal}` : `exit ${code ?? 'unknown'}`}）`));
    });
  });
}

const options = parseArguments(process.argv.slice(2));
const selectedEnvPath = resolveEnvPath(options.env, repositoryRoot, backendEnvPath);
const selectedEnv = readEnvFile(selectedEnvPath);

// 读取构建键值：显式环境变量 > 所选 env 文件。
// 使用 dev 之外的环境（如 production）时，四个公开地址必须显式提供，
// 绝不回退到本地 backend/.env，避免静默打出指向 127.0.0.1 的错误产物。
const isNonDevEnv = Boolean(options.env) && options.env !== 'dev';
function valueOf(key, { required = false } = {}) {
  const explicit = process.env[key];
  if (explicit !== undefined && explicit !== '') {
    return String(explicit).trim();
  }

  const raw = selectedEnv[key];
  if (raw) {
    return String(raw).trim();
  }

  if (required && isNonDevEnv) {
    throw new Error(
      `使用 ${options.env} 环境（${selectedEnvPath}）构建时缺少 ${key}，生产地址不得回退到本地 backend/.env。`,
    );
  }

  return String(raw || '').trim();
}

const apiOrigin = normalizeOrigin('APP_URL', valueOf('APP_URL', { required: true }));
const websiteOrigin = normalizeOrigin('FRONTEND_URL', valueOf('FRONTEND_URL', { required: true }));
const consoleOrigin = normalizeOrigin('CLIENT_CONSOLE_URL', valueOf('CLIENT_CONSOLE_URL', { required: true }));
const adminOrigin = normalizeOrigin('ADMIN_URL', valueOf('ADMIN_URL', { required: true }));
const origins = [apiOrigin, websiteOrigin, consoleOrigin, adminOrigin];

if (new Set(origins).size !== origins.length) {
  throw new Error('APP_URL、FRONTEND_URL、CLIENT_CONSOLE_URL、ADMIN_URL 必须为四个不同的 origin。');
}

if (new Set(origins.map((origin) => new URL(origin).protocol)).size !== 1) {
  throw new Error('四个公开地址必须使用同一协议；请统一使用 HTTP 或 HTTPS，避免浏览器混合内容。');
}

const selectedApplications = options.target === 'all'
  ? Object.entries(applications)
  : [[options.target, applications[options.target]]];
const baseBuildEnvironment = {
  ...process.env,
  VITE_BASE_URL: '/',
  VITE_API_BASE_URL: `${apiOrigin}/api`,
  VITE_PUBLIC_SITE_URL: websiteOrigin,
  VITE_CONSOLE_SITE_URL: consoleOrigin,
  VITE_SESSION_COOKIE_DOMAIN: valueOf('CLIENT_SESSION_COOKIE_DOMAIN'),
};

if (options.dryRun) {
  console.log(`环境文件: ${selectedEnvPath}`);
  console.log(`API: ${apiOrigin}`);
  console.log(`API base: ${baseBuildEnvironment.VITE_API_BASE_URL}`);
  console.log(`WWW: ${websiteOrigin}`);
  console.log(`Console: ${consoleOrigin}`);
  console.log(`Admin: ${adminOrigin}`);
  console.log(`构建目标: ${selectedApplications.map(([name]) => name).join(', ')}`);
  process.exit(0);
}

for (const [name, application] of selectedApplications) {
  console.log(`构建 ${name} (${application.workspace})...`);
  await runNpm(
    ['run', 'build', '--workspace', application.workspace],
    {
      ...baseBuildEnvironment,
      [application.assetVariable]: '/',
    },
  );
}

console.log('三个前端保持独立 dist，未写入 backend/public。');
