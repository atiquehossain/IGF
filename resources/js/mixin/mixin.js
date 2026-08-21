
export default {
  methods: {
    displayText: function (html, limit = 510) {
      try {
        const newData = html.replace(/<[^>]*>?/gm, '');
        const slice = newData.slice(0, limit) + '...';
        return slice.trim();
      } catch {
        return '';
      }
    },
    displayImage: function (html) {
      try {
        const root = document.createElement('div');
        root.innerHTML = html;
        const img = root.getElementsByTagName('img');
        if (img.length > 0) {
          return img[0].src;
        } else {
          return '/image/no-image.png';
        }
      } catch {
        return '/image/no-image.png';
      }
    }
  }
};
