import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '..', '..');
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

function parseArguments(argv) {
  const options = { dryRun: false, target: 'all' };

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

    throw new Error(`不支持的参数：${argument}`);
  }

  if (options.target !== 'all' && !Object.hasOwn(applications, options.target)) {
    throw new Error('--target 只能是 all、admin、www 或 console。');
  }

  return options;
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
const selectedApplications = options.target === 'all'
  ? Object.entries(applications)
  : [[options.target, applications[options.target]]];

// 每个前端构建时读取各自目录下的 .env（build 地址），不依赖 backend 环境文件注入。
// 本地开发（npm run dev）读取各端 .env.dev（--mode dev）。
const buildEnvironment = {
  ...process.env,
  VITE_BASE_URL: '/',
};

if (options.dryRun) {
  console.log(`构建目标: ${selectedApplications.map(([name]) => name).join(', ')}`);
  console.log('每个前端构建时读取各自目录下的 .env，不依赖 backend 环境文件。');
  process.exit(0);
}

for (const [name, application] of selectedApplications) {
  console.log(`构建 ${name} (${application.workspace})...`);
  await runNpm(
    ['run', 'build', '--workspace', application.workspace],
    {
      ...buildEnvironment,
      [application.assetVariable]: '/',
    },
  );
}

console.log('三个前端保持独立 dist，未写入 backend/public。');
