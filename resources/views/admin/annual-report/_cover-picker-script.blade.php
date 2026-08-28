<script>
  (() => {
    const select = document.querySelector('[data-report-cover-select]');
    const preview = document.querySelector('[data-report-cover-preview]');
    if (!select || !preview) return;

    const refresh = () => {
      const option = select.options[select.selectedIndex];
      const url = option ? option.dataset.imageUrl || '' : '';
      if (url) preview.src = url;
      else preview.removeAttribute('src');
      preview.style.display = url ? '' : 'none';
    };

    select.addEventListener('change', refresh);
    refresh();
  })();
</script>
