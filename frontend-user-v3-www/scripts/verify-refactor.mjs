import { spawn } from 'node:child_process'

function run(command, args) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      stdio: 'inherit',
      shell: process.platform === 'win32',
    })

    child.on('exit', (code) => {
      if (code === 0) {
        resolve()
        return
      }

      reject(new Error(`${command} ${args.join(' ')} failed with code ${code}`))
    })
  })
}

await run('node', ['./scripts/check-source-health.mjs'])
// 组件体系迁移后新增：模板里的每个 <t-xxx> 都必须被 scripts/tdesign-resolver.mjs 解析到
// 真实存在的 es 子入口，否则构建会静默产出未注册的自定义元素（渲染成空标签，不报错）。
await run('node', ['./scripts/check-tdesign-resolver.mjs'])
// 迁移收尾新增：旧组件体系（Element Plus）的依赖、标签、CSS 变量与旧脚本一旦回流，
// 构建不会失败（未注册的自定义元素只渲染成空标签），必须显式拦截。
await run('node', ['./scripts/check-no-legacy-ui.mjs'])
await run('npm', ['run', 'build'])
// 构建完成后再扫一遍产物：源码层的守卫看不到通过 node_modules 间接路径
// 打进 dist 的旧样式，只有对产物本身检查才能证明「现网构建不含旧组件体系」。
await run('node', ['./scripts/check-no-legacy-ui.mjs', '--dist'])
