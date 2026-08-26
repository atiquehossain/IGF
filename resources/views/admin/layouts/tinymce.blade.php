<script src="{{ asset('admin-assets/tinymce/tinymce.min.js') }}"></script>
<?php
$content_style = ' ';
if (!empty(@$contentStyle)) {
    $content_style = trim(preg_replace('/\s\s+/', ' ', @$contentStyle));
}
$editor_height = isset($editorHeight) ? max(240, min(900, (int) $editorHeight)) : 550;
$editor_toolbar = $editorToolbar ?? 'bootstrap restoreraft insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent image media link | vuetify2grid custombutton customvue emoticons fontawesome';
$editor_menubar = isset($editorMenubar) ? (bool) $editorMenubar : true;
?>
<script>
  var assetUrl = "{{ asset('/') }}";
  var content_style = ' {{ $content_style }} ';
  var editor_config = {
    path_absolute: "/",
    selector: 'textarea.my-editor',
    height: {{ $editor_height }},
    menubar: @json($editor_menubar),
    relative_urls: false,
    image_dimensions: false,
    body_class: 'v-application v-application--wrap',
    inline_styles: true,
    table_default_attributes: {
      class: 'table table-bordered'
    },
    table_use_colgroups: true,
    table_style_by_css: false,
    image_class_list: [{
        title: 'None',
        value: ''
      },
      {
        title: 'Fluid',
        value: 'img-fluid'
      },
      {
        title: 'Width 100%',
        value: 'w-100'
      },
      {
        title: 'Thumbnail',
        value: 'img-thumbnail'
      },
      {
        title: 'Rounded',
        value: 'rounded'
      },
      {
        title: 'Circle',
        value: 'rounded-circle '
      },
      {
        title: 'Fluid-Thumbnail',
        value: 'img-fluid img-thumbnail'
      },
      {
        title: 'Fluid-Circle',
        value: 'img-fluid rounded-circle'
      },
      {
        title: 'Fluid-Rounded',
        value: 'img-fluid rounded'
      },
    ],
    table_class_list: [{
        title: 'None',
        value: 'table'
      },
      {
        title: 'Table Striped',
        value: 'table table-striped'
      },
      {
        title: 'Table borders',
        value: 'table table-bordered'
      },
      {
        title: 'Table Striped Hover',
        value: 'table table-striped table-hover'
      },
      {
        title: 'Table borders Hover',
        value: 'table table-bordered table-hover'
      },
      {
        title: 'Acess table',
        value: 'acess-table-1'
      }

    ],
    external_plugins: {},
    body_class: 'v-application v-application--wrap',
    inline_styles: true,
    content_style: [
      content_style
    ],
    content_css: [
      assetUrl + 'admin-assets/assets/css/bootstrap.min.css',
      assetUrl + 'admin-assets/assets/css/vuetify.min.css',
      'https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
      assetUrl + 'css/app.css'
    ],
    theme: 'silver',
    schema: 'html5',
    noneditable_noneditable_class: 'fa',
    extended_valid_elements: 'span[class|style]',
    plugins: [
      "fontawesome noneditable emoticons pagebreak",
      "advlist autolink autosave lists link image charmap print preview hr anchor pagebreak",
      "searchreplace wordcount visualblocks visualchars code fullscreen",
      "insertdatetime media nonbreaking save table directionality vuetify2grid custombutton customvue",
      "emoticons template paste textpattern"
    ],
    toolbar: @json($editor_toolbar),
    file_picker_callback: function(callback, value, meta) {
      var x = window.innerWidth || document.documentElement.clientWidth || document.getElementsByTagName(
        'body')[0].clientWidth;
      var y = window.innerHeight || document.documentElement.clientHeight || document
        .getElementsByTagName('body')[0].clientHeight;

      var cmsURL = editor_config.path_absolute + 'admin/filemanager?editor=' + meta.fieldname;
      if (meta.filetype == 'image') {
        cmsURL = cmsURL + "&type=Images";
      } else {
        cmsURL = cmsURL + "&type=Files";
      }

      tinyMCE.activeEditor.windowManager.openUrl({
        url: cmsURL,
        title: 'Filemanager',
        width: x * 0.8,
        height: y * 0.8,
        resizable: "yes",
        close_previous: "no",
        onMessage: (api, message) => {
          callback(message.content);
        }
      });
    },
    setup: function(editor) {
      // Keep the original textarea current while TinyMCE owns the visible
      // editor. This lets native `required` validation run before the form's
      // submit event without rejecting content that is already on screen.
      var synchronizeTextarea = function() {
        editor.save();
      };

      editor.on('init input change SetContent Undo Redo', synchronizeTextarea);
    }
  };

  tinymce.init(editor_config);
</script>
