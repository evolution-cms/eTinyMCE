(function () {
    var cfg = window.eTinyMCEConfig || {};
    var queue = cfg.queue || [];
    if (!queue.length) {
        return;
    }

    if (!window.eTinyMCEFilePicker) {
        var opener = cfg.opener || 'tinymce';
        window.eTinyMCEFilePicker = function (callback, value, meta) {
            var type = 'images';
            var title = 'Image';
            if (meta && meta.filetype === 'file') {
                type = 'files';
                title = 'File';
            }

            var managerUrl = (window.modx && window.modx.MODX_MANAGER_URL) || window.MODX_MANAGER_URL || '';
            var url = managerUrl + 'media/browser/mcpuk/browse.php?opener=' + opener + '&field=src&type=' + type;

            window.tinymceCallBackURL = '';
            tinymce.activeEditor.windowManager.open({
                title: title,
                size: 'large',
                body: {
                    type: 'panel',
                    items: [{
                        type: 'htmlpanel',
                        html: '<iframe id="filemanager_iframe-popup" src="' + url + '" frameborder="0" style="width:100%;height:100%"></iframe>'
                    }]
                },
                buttons: [],
                onClose: function () {
                    if (window.tinymceCallBackURL) {
                        callback(window.tinymceCallBackURL, {});
                    }
                }
            });
        };
    }

    if (!window.eTinyMCESetup) {
        window.eTinyMCESetup = function (ed) {
            ed.on('change', function () {
                window.documentDirty = true;
            });
        };
    }

    var profiles = window.eTinyMCEProfiles || {};
    var defaultKey = cfg.defaultProfile || '';

    queue.forEach(function (item) {
        var profileKey = item.profile;
        if (!profiles[profileKey]) {
            if (profiles[defaultKey]) {
                console.warn('eTinyMCE profile missing: ' + profileKey + '. Using default.');
                profileKey = defaultKey;
            } else {
                console.warn('eTinyMCE profiles not loaded.');
                return;
            }
        }

        var profileOptions = profiles[profileKey] || {};
        var baseOptions = {
            selector: item.selectors,
            file_picker_callback: window.eTinyMCEFilePicker,
            setup: window.eTinyMCESetup
        };

        var initOptions = Object.assign({}, profileOptions, item.options || {}, baseOptions);
        tinymce.init(initOptions);
    });
})();
