/*
 *	tiny-bs-grid https://github.com/jeffhehe/tiny-bs-grid
 *  this works for TinyMCE5.* on Bootstrap 4.*
 *  Version: v0.3
 *  Author: Jeff Wang
 *  Date: April 24, 2019
 */

 (function () {
    tinymce.PluginManager.add('custombutton', custom_buttonPlugin);

    function custom_buttonPlugin(editor, url) {

      editor.ui.registry.addToggleButton('custombutton', {
        text: 'Add Button',
        tooltip: 'Add Button',
        onAction: custom_button,
      });

      function custom_button() {
        editor.windowManager.open({
            title: 'Add custom button',
            body: {
                type: 'panel',
                items: [{
                    type: 'input',
                    name: 'button_label',
                    label: 'Button label',
                    flex: true
                }, {
                    type: 'input',
                    name: 'button_href',
                    label: 'Button href',
                    flex: true
                }, {
                    type: 'selectbox',
                    name: 'button_target',
                    label: 'Target',
                    items: [{
                            text: 'None',
                            value: ''
                        },
                        {
                            text: 'New window',
                            value: '_blank'
                        },
                        {
                            text: 'Self',
                            value: '_self'
                        },
                        {
                            text: 'Parent',
                            value: '_parent'
                        }
                    ],
                    flex: true
                }, {
                    type: 'selectbox',
                    name: 'button_rel',
                    label: 'Rel',
                    items: [{
                            text: 'No value',
                            value: ''
                        },
                        {
                            text: 'Alternate',
                            value: 'alternate'
                        },
                        {
                            text: 'Help',
                            value: 'help'
                        },
                        {
                            text: 'Manifest',
                            value: 'manifest'
                        },
                        {
                            text: 'No follow',
                            value: 'nofollow'
                        },
                        {
                            text: 'No opener',
                            value: 'noopener'
                        },
                        {
                            text: 'No referrer',
                            value: 'noreferrer'
                        },
                        {
                            text: 'Opener',
                            value: 'opener'
                        }
                    ],
                    flex: true
                }, {
                    type: 'selectbox',
                    name: 'button_style',
                    label: 'Style',
                    items: [{
                            text: 'Success',
                            value: 'success'
                        },
                        {
                            text: 'Info',
                            value: 'info'
                        },
                        {
                            text: 'Warning',
                            value: 'warning'
                        },
                        {
                            text: 'Error',
                            value: 'error'
                        },
                        {
                            text: 'Read More',
                            value: 'read-more'
                        }
                    ],
                    flex: true
                }]
            },
            onSubmit: function(api) {
                var btn = "btn btn-";
                if (api.getData().button_style == 'read-more') {
                    btn = '';
                }
                var html = '<a href="' + api.getData().button_href +
                    '" class="' + btn + api.getData().button_style +
                    '" rel="' + api.getData().button_rel + '" target="' +
                    api.getData().button_target + '">' + api.getData()
                    .button_label + '</a>';

                // insert markup
                editor.insertContent(html);

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

      };
    }
  })();
