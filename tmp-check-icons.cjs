const icons = require('@element-plus/icons-vue');
const names = Object.keys(icons);
const keywords = ['Monitor', 'Box', 'Grid', 'Setting', 'Menu', 'Goods', 'Service', 'Platform', 'List', 'Operation', 'Management', 'DataLine', 'Document'];
const found = names.filter(n => keywords.some(k => n.includes(k)));
console.log(found.join('\n'));
console.log('---');
console.log('Total icons:', names.length);
