import { defineComponent, h } from 'vue';
import { CopyIcon } from 'tdesign-icons-vue-next';

export const InfoCell = defineComponent({
  name: 'InfoCell',
  props: {
    label: { type: String, required: true },
    value: { type: String, required: true },
    strong: { type: Boolean, default: false },
    copyable: { type: Boolean, default: false },
    warning: { type: Boolean, default: false },
  },
  emits: ['copy'],
  setup(props, { emit }) {
    return () =>
      h('div', { class: 'detail-cell' }, [
        h('span', props.label),
        h('div', { class: ['detail-cell-value', { 'is-warning': props.warning }] }, [
          props.strong ? h('strong', props.value) : h('span', props.value),
          props.copyable && props.value && props.value !== '--'
            ? h(
                'button',
                {
                  type: 'button',
                  class: 'copy-link',
                  title: `复制${props.label}`,
                  'aria-label': `复制${props.label}`,
                  onClick: () => emit('copy', props.value),
                },
                [h(CopyIcon, { size: '1.125rem' })],
              )
            : null,
        ]),
      ]);
  },
});
