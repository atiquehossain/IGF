export default {
  data() {
    return {
      igfLocale: 'en',
      isLogin: false,
      authUser: {}
    };
  },
  mounted() {
    this.igfLocale = this.$page.props.locale ?? 'en';
    this.authUser = this.$page.props.user;
    if (this.authUser) {
      this.isLogin = true;
    } else {
      this.isLogin = false;
    }
  },
  watch: {
    $page: {
      handler() {
        this.authUser = this.$page.props.user;
        if (this.authUser) {
          this.isLogin = true;
        } else {
          this.isLogin = false;
        }
      }
    }
  },
  methods: {
    /**
         * Translate the given key.
         */
    __(key, replace = {}) {
      let translation = this.$page.props.language?.[key]
        ? this.$page.props.language[key]
        : key;

      Object.keys(replace).forEach(function (key) {
        translation = translation.replace(':' + key, replace[key]);
      });

      return translation;
    },

    /**
         * Translate the given key with basic pluralization.
         */
    __n(key, number, replace = {}) {
      const options = key.split('|');

      key = options[1];
      if (number === 1) {
        key = options[0];
      }

      return this.__(key, replace);
    },
    bytesToSize(bytes, decimals = 2, binaryUnits) {
      const k = 1024;
      if (bytes === 0) return '0 Bytes';
      const unitMultiple = binaryUnits ? k : 1000;
      const unitNames =
        unitMultiple === k
          ? ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB']
          : ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
      const unitChanges = Math.floor(Math.log(bytes) / Math.log(unitMultiple));
      return (
        parseFloat(
          (bytes / Math.pow(unitMultiple, unitChanges)).toFixed(decimals || 0)
        ) +
        ' ' +
        unitNames[unitChanges]
      );
    },
    Lang() {
      return this.$page.props.language;
    }
  }
};
