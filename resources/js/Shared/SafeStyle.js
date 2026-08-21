import { h } from 'vue';

export default {
  name: 'SafeStyle',
  props: {
    css: {
      type: String,
      default: '',
    },
    elementId: {
      type: String,
      default: undefined,
    },
  },
  setup(props) {
    return () => props.css
      ? h('style', { id: props.elementId }, props.css)
      : null;
  },
};
