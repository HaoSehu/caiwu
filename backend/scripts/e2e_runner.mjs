/**
 * 三端共用的 e2e 运行器：自己托管 vite preview 生命周期。
 *
 * 背景：Windows 下 Playwright 的 webServer 在结束阶段无法可靠回收 preview 进程树，
 * 会出现「用例已全部跑完但命令不退出」——因为 Playwright 会一直等 4173 端口释放。
 * 这里改为显式托管：起服务 → 等端口就绪 → 跑 playwright → 按进程树强杀服务 → 以用例退出码退出。
 *
 * 三端固定使用 4173 端口，因此 e2e 必须串行执行，并发会互抢端口直接失败。
 * 依赖可执行文件用 createRequire 从各端 package.json 解析，不假设依赖一定被提升到仓库根
 * node_modules（某端单独 npm install 时同样可用）。
 */
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { createConnection } from 'node:net';
import path from 'node:path';
import process from 'node:process';

const HOST = '127.0.0.1';
const PORT = 4173;

const isWindows = process.platform === 'win32';

/**
 * 解析应用依赖包声明的可执行入口。
 * 不直接 resolve('<包>/<文件>')：vite 与 @playwright/test 的 exports 未导出子路径，
 * 会报 ERR_PACKAGE_PATH_NOT_EXPORTED，必须先拿 package.json 再按其 bin 字段拼路径。
 */
export function resolveBin(cwd, packageName) {
  const requireFromApp = createRequire(path.join(cwd, 'package.json'));
  const manifestPath = requireFromApp.resolve(`${packageName}/package.json`);
  const manifest = requireFromApp(manifestPath);
  const binField = typeof manifest.bin === 'string' ? manifest.bin : Object.values(manifest.bin ?? {})[0];

  if (!binField) {
    throw new Error(`依赖 ${packageName} 未声明 bin 入口`);
  }

  return path.join(path.dirname(manifestPath), binField);
}

/** 解析当前应用的 vite 与 Playwright 可执行入口。 */
export function resolveE2EBins(cwd) {
  return {
    viteBin: resolveBin(cwd, 'vite'),
    playwrightBin: resolveBin(cwd, '@playwright/test'),
  };
}

function waitForPort(timeoutMs = 60_000) {
  const deadline = Date.now() + timeoutMs;
  return new Promise((resolve, reject) => {
    const attempt = () => {
      const socket = createConnection({ host: HOST, port: PORT });
      socket.once('connect', () => {
        socket.destroy();
        resolve();
      });
      socket.once('error', () => {
        socket.destroy();
        if (Date.now() > deadline) {
          reject(new Error(`等待 ${HOST}:${PORT} 就绪超时`));
          return;
        }
        setTimeout(attempt, 200);
      });
    };
    attempt();
  });
}

/** 端口当前是否已被监听（用于启动前预检）。 */
function isPortListening(timeoutMs = 800) {
  return new Promise((resolve) => {
    const socket = createConnection({ host: HOST, port: PORT });
    const done = (listening) => {
      socket.destroy();
      resolve(listening);
    };
    socket.once('connect', () => done(true));
    socket.once('error', () => done(false));
    setTimeout(done, timeoutMs, false);
  });
}

function killTree(child) {
  if (!child || child.exitCode !== null || child.signalCode !== null) {
    return Promise.resolve();
  }
  return new Promise((resolve) => {
    if (isWindows) {
      // 直接对进程树强杀，避免 preview 残留占住端口
      const killer = spawn('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' });
      killer.once('exit', () => resolve());
      killer.once('error', () => resolve());
      return;
    }
    child.once('exit', () => resolve());
    child.kill('SIGTERM');
  });
}

/**
 * 跑一端的 e2e：起 vite preview → 等端口 → 跑 playwright test → 强杀 → 以用例退出码退出。
 * @param {{ appName: string, cwd: string }} options appName 只用于失败提示，cwd 为应用根目录
 */
export async function runE2E({ appName, cwd }) {
  const passthroughArgs = process.argv.slice(2);
  const { viteBin, playwrightBin } = resolveE2EBins(cwd);

  // 启动前预检：端口若已被占用（常见于上次残留的 preview），我们起的 vite 会因
  // --strictPort 立刻退出，而后续探测仍会连上那个旧服务 → 用例跑在过期产物上（假阳性）。
  // 这里直接拒绝，等价于原 Playwright 配置的 reuseExistingServer: false。
  if (await isPortListening()) {
    throw new Error(`[${appName}] 端口 ${PORT} 已被占用（可能是上次残留的 preview 进程），请先结束占用该端口的进程再重试`);
  }

  const server = spawn(
    process.execPath,
    [viteBin, 'preview', '--outDir', 'dist-e2e', '--port', String(PORT), '--strictPort', '--host', HOST],
    { cwd, stdio: 'inherit' },
  );

  let serverExitError = null;
  server.once('exit', (code) => {
    if (code !== 0 && code !== null) {
      serverExitError = new Error(`[${appName}] vite preview 异常退出，退出码 ${code}`);
    }
  });

  try {
    await waitForPort();
  } catch (error) {
    await killTree(server);
    throw serverExitError || error;
  }

  // 二次保险：端口就绪后再给子进程一点时间暴露退出（预检与实际监听之间存在极小竞态窗口）。
  await new Promise((resolve) => setTimeout(resolve, 300));
  if (server.exitCode !== null || server.signalCode !== null) {
    await killTree(server);
    throw serverExitError || new Error(`[${appName}] 端口 ${PORT} 就绪前 vite preview 已退出`);
  }

  const runner = spawn(process.execPath, [playwrightBin, 'test', ...passthroughArgs], { cwd, stdio: 'inherit' });

  const exitCode = await new Promise((resolve) => {
    runner.once('exit', (code) => resolve(code ?? 1));
    runner.once('error', () => resolve(1));
  });

  await killTree(server);
  process.exit(exitCode);
}
