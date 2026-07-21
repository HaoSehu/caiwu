import { cp, mkdir, mkdtemp, rename, rm, stat } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '..', '..');
const backendRoot = path.resolve(repositoryRoot, 'backend');
const publicRoot = path.resolve(backendRoot, 'public');
const npmCommand = process.platform === 'win32' ? 'npm.cmd' : 'npm';

const applications = [
  {
    workspace: 'chuangouyun-user-v3-www',
    source: path.resolve(repositoryRoot, 'frontend-user-v3-www', 'dist'),
    target: 'frontend/site',
    assetBase: '/frontend/site/',
    assetVariable: 'VITE_WWW_ASSET_BASE_URL',
  },
  {
    workspace: 'chuangouyun-user-v4-console',
    source: path.resolve(repositoryRoot, 'frontend-user-v4-console', 'dist'),
    target: 'frontend/client',
    assetBase: '/frontend/client/',
    assetVariable: 'VITE_CONSOLE_ASSET_BASE_URL',
  },
  {
    workspace: 'chuangouyun-admin-v3',
    source: path.resolve(repositoryRoot, 'frontend-admin-v3', 'dist'),
    target: 'frontend/admin',
    assetBase: '/frontend/admin/',
    assetVariable: 'VITE_ADMIN_ASSET_BASE_URL',
  },
];

const rootPublicFiles = ['branding', 'img', 'robots.txt', 'sitemap.xml'];
const legacyPublishedDirectories = ['site', 'client', 'admin'];

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
      const unquoted = rawValue.length >= 2
        && ((rawValue.startsWith('"') && rawValue.endsWith('"')) || (rawValue.startsWith("'") && rawValue.endsWith("'")))
        ? rawValue.slice(1, -1)
        : rawValue;

      env[key] = unquoted;
      return env;
    }, {});
}

function parseArguments(argv) {
  const options = { dryRun: false, siteUrl: '' };

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];

    if (argument === '--dry-run') {
      options.dryRun = true;
      continue;
    }

    if (argument === '--site-url') {
      options.siteUrl = argv[index + 1] || '';
      index += 1;
      continue;
    }

    if (argument.startsWith('--site-url=')) {
      options.siteUrl = argument.slice('--site-url='.length);
      continue;
    }

    throw new Error(`不支持的参数：${argument}`);
  }

  return options;
}

function normalizeSiteUrl(rawUrl) {
  let url;

  try {
    url = new URL(String(rawUrl || '').trim());
  } catch {
    throw new Error('未能解析统一站点地址；请设置 backend/.env 的 APP_URL，或传入 --site-url=https://你的域名');
  }

  if (!['http:', 'https:'].includes(url.protocol) || !url.hostname || url.username || url.password) {
    throw new Error('统一站点地址必须是无账号密码的 HTTP(S) 根域名');
  }

  if (url.pathname !== '/' || url.search || url.hash) {
    throw new Error('统一站点地址不能包含路径、查询参数或片段');
  }

  return url.origin;
}

function publishedPath(root, relativePath) {
  const target = path.resolve(root, relativePath);
  const relative = path.relative(root, target);

  if (!relative || relative.startsWith(`..${path.sep}`) || path.isAbsolute(relative)) {
    throw new Error(`非法发布路径：${relativePath}`);
  }

  return target;
}

async function pathExists(target) {
  try {
    await stat(target);
    return true;
  } catch {
    return false;
  }
}

function runNpm(args, env) {
  return new Promise((resolve, reject) => {
    const child = spawn(npmCommand, args, {
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

async function replacePublishedPath(root, name, stagedPath) {
  const target = publishedPath(root, name);
  const backup = path.join(path.dirname(target), `.${path.basename(target)}.previous`);

  await mkdir(path.dirname(target), { recursive: true });

  await rm(backup, { recursive: true, force: true });

  if (await pathExists(target)) {
    await rename(target, backup);
  }

  try {
    await rename(stagedPath, target);
  } catch (error) {
    if (await pathExists(backup) && !await pathExists(target)) {
      await rename(backup, target);
    }
    throw error;
  }

  await rm(backup, { recursive: true, force: true });
}

async function copyIfExists(source, destination) {
  if (await pathExists(source)) {
    await mkdir(path.dirname(destination), { recursive: true });
    await cp(source, destination, { recursive: true });
  }
}

const options = parseArguments(process.argv.slice(2));
const backendEnv = readEnvFile(path.resolve(backendRoot, '.env'));
const configuredUrl = options.siteUrl || process.env.INTEGRATED_SITE_URL || process.env.APP_URL || backendEnv.APP_URL;
const siteUrl = normalizeSiteUrl(configuredUrl);

const baseBuildEnvironment = {
  ...process.env,
  VITE_BASE_URL: '/',
  VITE_API_BASE_URL: '/api',
  VITE_API_URL_PREFIX: '/api',
  VITE_IS_REQUEST_PROXY: 'true',
  VITE_PUBLIC_SITE_URL: siteUrl,
  VITE_CONSOLE_SITE_URL: siteUrl,
  VITE_SESSION_COOKIE_DOMAIN: '',
};

if (options.dryRun) {
  console.log(`统一站点地址：${siteUrl}`);
  console.log('访问路径：/ -> site，/client -> client，/admin -> admin，/vnc -> vnc');
  console.log('静态目录：backend/public/frontend/{site,client,admin}');
  process.exit(0);
}

for (const application of applications) {
  console.log(`构建 ${application.workspace}（${application.assetBase}）...`);
  await runNpm(
    ['run', 'build', '--workspace', application.workspace],
    {
      ...baseBuildEnvironment,
      [application.assetVariable]: application.assetBase,
    },
  );
}

const stagingRoot = await mkdtemp(path.join(os.tmpdir(), 'caiwu-integrated-frontends-'));
const publicStageRoot = path.join(stagingRoot, 'public');

try {
  for (const application of applications) {
    if (!await pathExists(application.source)) {
      throw new Error(`未找到构建产物：${application.source}`);
    }

    await copyIfExists(application.source, path.join(publicStageRoot, application.target));
  }

  const publicSiteStage = path.join(publicStageRoot, 'frontend', 'site');
  for (const fileName of rootPublicFiles) {
    await copyIfExists(path.join(publicSiteStage, fileName), path.join(publicStageRoot, fileName));
  }

  const consoleVncSource = path.join(publicStageRoot, 'frontend', 'client', 'vnc');
  if (!await pathExists(consoleVncSource)) {
    throw new Error(`未找到 VNC 静态资源：${consoleVncSource}`);
  }
  await cp(consoleVncSource, path.join(publicStageRoot, 'vnc'), { recursive: true });
  await rm(consoleVncSource, { recursive: true, force: true });

  for (const application of applications) {
    await replacePublishedPath(publicRoot, application.target, path.join(publicStageRoot, application.target));
  }
  await replacePublishedPath(publicRoot, 'vnc', path.join(publicStageRoot, 'vnc'));

  for (const fileName of rootPublicFiles) {
    const stagedFile = path.join(publicStageRoot, fileName);
    if (await pathExists(stagedFile)) {
      await replacePublishedPath(publicRoot, fileName, stagedFile);
    }
  }

  await Promise.all(legacyPublishedDirectories.map((name) => rm(publishedPath(publicRoot, name), { recursive: true, force: true })));

  console.log(`前端已发布到 ${publicRoot}`);
} finally {
  await rm(stagingRoot, { recursive: true, force: true });
}
