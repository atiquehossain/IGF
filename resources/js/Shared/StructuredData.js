import { h } from 'vue';
import { Head } from '@inertiajs/vue3';

const escapeScriptText = (value) => value
  .replace(/</g, '\\u003c')
  .replace(/>/g, '\\u003e')
  .replace(/&/g, '\\u0026')
  .replace(/\u2028/g, '\\u2028')
  .replace(/\u2029/g, '\\u2029');

export default {
  name: 'StructuredData',
  props: {
    json: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    return () => h(Head, null, {
      default: () => [
        h('script', {
          'head-key': 'structured-data',
          type: 'application/ld+json',
        }, escapeScriptText(props.json)),
      ],
    });
  },
};
