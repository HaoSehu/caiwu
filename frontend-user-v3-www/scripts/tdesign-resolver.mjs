// TDesign Vue Next 按需组件解析器：www 端专用。
//
// 为什么不用官方 TDesignResolver：它的 from 恒为库根（barrel，如 library 默认的
// "tdesign-vue"、本项目选项对应的 "tdesign-vue-next"），
// 会把所有组件入口与样式拉进构建，CSS 直接全量进包，首屏与样式分片门禁必然爆。
// 这里改为把模板里的 <t-xxx> 解析到 tdesign-vue-next/es/<组件目录>/index：
// 子入口自带 import './style/css.mjs'，只带入该组件自己的样式。
//
// 表数据取自 tdesign-vue-next/es/index.mjs 的导出语句，约四成导出名的目录不是 kebab-case
// （Col→grid、DescriptionsItem→descriptions、TabPanel→tabs、Option→select 等）。
// 与 frontend-admin-v3/vite.config.ts、frontend-user-v4-console/vite.config.ts 的同名表保持同步。
export const TDESIGN_COMPONENT_DIRS = {
  Affix: 'affix',
  Alert: 'alert',
  Anchor: 'anchor',
  AnchorItem: 'anchor',
  AnchorTarget: 'anchor',
  Aside: 'layout',
  AutoComplete: 'auto-complete',
  Avatar: 'avatar',
  AvatarGroup: 'avatar',
  BackTop: 'back-top',
  Badge: 'badge',
  BaseTable: 'table',
  Breadcrumb: 'breadcrumb',
  BreadcrumbItem: 'breadcrumb',
  Button: 'button',
  Calendar: 'calendar',
  Card: 'card',
  Cascader: 'cascader',
  CascaderPanel: 'cascader',
  Checkbox: 'checkbox',
  CheckboxGroup: 'checkbox',
  CheckTag: 'tag',
  CheckTagGroup: 'tag',
  Col: 'grid',
  Collapse: 'collapse',
  CollapsePanel: 'collapse',
  ColorPicker: 'color-picker',
  ColorPickerPanel: 'color-picker',
  Comment: 'comment',
  ConfigProvider: 'config-provider',
  Content: 'layout',
  DatePicker: 'date-picker',
  DatePickerPanel: 'date-picker',
  DateRangePicker: 'date-picker',
  DateRangePickerPanel: 'date-picker',
  Descriptions: 'descriptions',
  DescriptionsItem: 'descriptions',
  Dialog: 'dialog',
  DialogCard: 'dialog',
  Divider: 'divider',
  Drawer: 'drawer',
  Dropdown: 'dropdown',
  DropdownItem: 'dropdown',
  DropdownMenu: 'dropdown',
  Empty: 'empty',
  EnhancedTable: 'table',
  Footer: 'layout',
  Form: 'form',
  FormItem: 'form',
  Guide: 'guide',
  Header: 'layout',
  HeadMenu: 'menu',
  HighlightOption: 'auto-complete',
  Icon: 'icon',
  Image: 'image',
  ImageViewer: 'image-viewer',
  Input: 'input',
  InputAdornment: 'input-adornment',
  InputGroup: 'input',
  InputNumber: 'input-number',
  Layout: 'layout',
  Link: 'link',
  List: 'list',
  ListItem: 'list',
  ListItemMeta: 'list',
  Loading: 'loading',
  Menu: 'menu',
  MenuGroup: 'menu',
  MenuItem: 'menu',
  Message: 'message',
  Notification: 'notification',
  Option: 'select',
  OptionGroup: 'select',
  Pagination: 'pagination',
  PaginationMini: 'pagination',
  Paragraph: 'typography',
  Popconfirm: 'popconfirm',
  Popup: 'popup',
  PrimaryTable: 'table',
  Progress: 'progress',
  QRCode: 'qrcode',
  Radio: 'radio',
  RadioButton: 'radio',
  RadioGroup: 'radio',
  RangeInput: 'range-input',
  RangeInputPopup: 'range-input',
  Rate: 'rate',
  Row: 'grid',
  Select: 'select',
  SelectInput: 'select-input',
  Skeleton: 'skeleton',
  Slider: 'slider',
  SliderButton: 'slider',
  Space: 'space',
  Statistic: 'statistic',
  StepItem: 'steps',
  Steps: 'steps',
  StickyItem: 'sticky-tool',
  StickyTool: 'sticky-tool',
  Submenu: 'menu',
  Swiper: 'swiper',
  SwiperItem: 'swiper',
  Switch: 'switch',
  Table: 'table',
  TabPanel: 'tabs',
  Tabs: 'tabs',
  Tag: 'tag',
  TagInput: 'tag-input',
  Text: 'typography',
  Textarea: 'textarea',
  Timeline: 'timeline',
  TimelineItem: 'timeline',
  TimePicker: 'time-picker',
  TimePickerPanel: 'time-picker',
  TimeRangePicker: 'time-picker',
  Title: 'typography',
  Tooltip: 'tooltip',
  Transfer: 'transfer',
  Tree: 'tree',
  TreeSelect: 'tree-select',
  Typography: 'typography',
  Upload: 'upload',
  Watermark: 'watermark',
};

// 函数式插件在 es 下是独立入口，不经组件目录；plugin 入口本身不带样式，
// 需要调用方（或入口样式清单）另行引入 es/<dir>/style/css.mjs。
export const TDESIGN_PLUGIN_DIRS = {
  DialogPlugin: 'dialog',
  LoadingPlugin: 'loading',
  MessagePlugin: 'message',
  NotifyPlugin: 'notification',
};

export function createTDesignResolver() {
  return {
    type: 'component',
    resolve(name) {
      const pluginDir = TDESIGN_PLUGIN_DIRS[name];
      if (pluginDir) {
        return { name, from: `tdesign-vue-next/es/${pluginDir}/plugin` };
      }
      if (!/^T[A-Z]/.test(name)) {
        return;
      }
      const importName = name.slice(1);
      const dir = TDESIGN_COMPONENT_DIRS[importName];
      if (!dir) {
        return;
      }
      return { name: importName, from: `tdesign-vue-next/es/${dir}/index` };
    },
  };
}
