import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const sourcePath = new URL('../../public/vnc/vnc.html', import.meta.url);
const source = readFileSync(sourcePath, 'utf8');

assert.doesNotMatch(source, /\/api\/(?:admin|client)\//);
assert.doesNotMatch(source, /admin_user_id/);
assert.match(source, /\/api\/v2\/client\/services\/\$\{id\}\/vnc/);
assert.match(source, /\/api\/v2\/client\/vnc-tokens\/\$\{encodeURIComponent\(freshToken\)\}/);
assert.match(source, /\/api\/v2\/client\/vnc-tokens\/\$\{encodeURIComponent\(token\)\}/);
assert.match(source, /new URL\(relayPath, `\$\{apiBase\}\/`\)/);
assert.doesNotMatch(source, /apiBase \|\| location\.origin/);
assert.match(source, /relayUrl\.protocol === 'https:'/);
assert.match(source, /relayUrl\.protocol === 'http:'/);

console.log('vnc API path tests passed');
