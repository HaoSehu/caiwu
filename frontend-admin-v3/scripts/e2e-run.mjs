/**
 * 管理端 e2e 运行器：自己托管 vite preview 生命周期。
 *
 * 背景：Windows 下 Playwright 的 webServer 在结束阶段无法可靠回收 preview 进程树，
 * 会出现「用例已全部跑完但命令不退出」——因为 Playwright 会一直等 4173 端口释放。
 * 这里改为显式托管：起服务 → 等端口就绪 → 跑 playwright → 按进程树强杀服务 → 以用例退出码退出。
 *
 * 用法：npm run test:e2e [-- <playwright 参数>]
 */
import { spawn } from 'node:child_process';
import { createConnection } from 'node:net';
import path from 'node:path';
import process from 'node:process';

const CWD = process.cwd();
const HOST = '127.0.0.1';
const PORT = 4173;
const VITE_BIN = path.resolve(CWD, '../node_modules/vite/bin/vite.js');
const PLAYWRIGHT_BIN = path.resolve(CWD, '../node_modules/@playwright/test/cli.js');

const isWindows = process.platform === 'win32';

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

async function main() {
  const passthroughArgs = process.argv.slice(2);

  // 启动前预检：端口若已被占用（常见于上次残留的 preview），我们起的 vite 会因
  // --strictPort 立刻退出，而后续探测仍会连上那个旧服务 → 用例跑在过期产物上（假阳性）。
  // 这里直接拒绝，等价于原 Playwright 配置的 reuseExistingServer: false。
  if (await isPortListening()) {
    throw new Error(`端口 ${PORT} 已被占用（可能是上次残留的 preview 进程），请先结束占用该端口的进程再重试`);
  }

  const server = spawn(
    process.execPath,
    [VITE_BIN, 'preview', '--outDir', 'dist-e2e', '--port', String(PORT), '--strictPort', '--host', HOST],
    { cwd: CWD, stdio: 'inherit' },
  );

  let serverExitError = null;
  server.once('exit', (code) => {
    if (code !== 0 && code !== null) {
      serverExitError = new Error(`vite preview 异常退出，退出码 ${code}`);
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
    throw serverExitError || new Error(`端口 ${PORT} 就绪前 vite preview 已退出`);
  }

  const runner = spawn(process.execPath, [PLAYWRIGHT_BIN, 'test', ...passthroughArgs], {
    cwd: CWD,
    stdio: 'inherit',
  });

  const exitCode = await new Promise((resolve) => {
    runner.once('exit', (code) => resolve(code ?? 1));
    runner.once('error', () => resolve(1));
  });

  await killTree(server);
  process.exit(exitCode);
}

main().catch((error) => {
  console.error(`[e2e] ${error instanceof Error ? error.message : String(error)}`);
  process.exit(1);
});
