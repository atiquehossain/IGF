
(function () {
    tinymce.PluginManager.add('customvue', customvuePlugin);

    function customvuePlugin(editor, url) {

        editor.ui.registry.addToggleButton('customvue', {
            text: 'Vue',
            tooltip: 'Add Vue Components',
            onAction: custom_vue,
        });

        function custom_vue() {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: "GET",
                url: editor_config.path_absolute +"admin/edito-draft-api",
                success: function (res) {
                    if (res.data) {
                        var items = res.data;
                        editor.windowManager.open({
                            title: 'Vue Components',
                            body: {
                                type: 'panel',
                                items: [{
                                    type: 'selectbox',
                                    name: 'vue_component',
                                    label: 'UI Components',
                                    items: items,
                                    flex: true
                                }]
                            },
                            onSubmit: function (api) {
                                var component = api.getData().vue_component;
                                editor.insertContent(component);
                                // close the dialog
                                api.close();
                            },
                            buttons: [{
                                text: 'Close',
                                type: 'cancel',
                                onclick: 'close'
                            },
                            {
                                text: 'Insert',
                                type: 'submit',
                                primary: true,
                                enabled: false
                            }
                            ]
                        });
                    }
                },
                error: function (err) {
                    toastrMsg('error', err.responseJSON.message);
                }
              })
        };
    }
})();
