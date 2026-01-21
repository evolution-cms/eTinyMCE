(function () {
    var cfg = window.eTinyMCEConfig || {};
    var queue = cfg.queue || [];
    if (!queue.length) {
        return;
    }

    var baseUrl = (cfg.baseUrl || '').toString();
    var fileManager = cfg.fileManager || {};
    var opener = cfg.opener || 'tinymce';
    var whichBrowser = normalizeString(cfg.whichBrowser || 'mcpuk').toLowerCase();

    function normalizeBaseUrl(value) {
        var base = (value || '').toString();
        if (!base) {
            return '';
        }
        base = base.replace(/\/+$/, '');
        if (base && base[0] !== '/') {
            base = '/' + base;
        }
        return base;
    }

    var normalizedBaseUrl = normalizeBaseUrl(baseUrl);

    function showMessage(editor, text, type) {
        if (editor && editor.notificationManager && typeof editor.notificationManager.open === 'function') {
            editor.notificationManager.open({ text: text, type: type || 'error' });
            return;
        }
        if (window.alert) {
            window.alert(text);
        }
    }

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function buildFileManagerUrl(meta) {
        var urlPrefix = normalizeString(fileManager.urlPrefix || 'filemanager');
        urlPrefix = urlPrefix.replace(/^\/+/, '');
        var type = (meta && meta.filetype === 'image') ? 'Images' : 'Files';
        var editorName = meta && typeof meta.fieldname === 'string' ? meta.fieldname : '';
        var url = normalizedBaseUrl;
        if (!url) {
            url = '';
        }
        if (urlPrefix) {
            url = url + '/' + urlPrefix;
        }
        if (!url) {
            url = '/' + urlPrefix;
        }
        var params = ['type=' + encodeURIComponent(type)];
        if (editorName) {
            params.push('editor=' + encodeURIComponent(editorName));
        }
        if (fileManager.cacheBust) {
            params.push('v=' + encodeURIComponent(String(fileManager.cacheBust)));
        }
        url += '?' + params.join('&');
        return url;
    }

    function isSignedQuery(query) {
        var lowered = query.toLowerCase();
        return lowered.indexOf('signature=') !== -1 ||
            lowered.indexOf('x-amz-signature=') !== -1 ||
            lowered.indexOf('x-amz-algorithm=') !== -1 ||
            lowered.indexOf('x-amz-credential=') !== -1 ||
            lowered.indexOf('x-amz-date=') !== -1 ||
            lowered.indexOf('x-amz-expires=') !== -1 ||
            lowered.indexOf('x-amz-security-token=') !== -1 ||
            lowered.indexOf('expires=') !== -1;
    }

    function stripSignedQuery(url) {
        var parts = url.split('?');
        if (parts.length < 2) {
            return url;
        }
        var query = parts.slice(1).join('?');
        if (!isSignedQuery(query)) {
            return url;
        }
        return parts[0];
    }

    function normalizeSelectedUrl(url) {
        var normalized = normalizeString(url);
        if (!normalized) {
            return '';
        }
        if (!fileManager.allowSignedUrls) {
            normalized = stripSignedQuery(normalized);
        }
        var strategy = normalizeString(fileManager.urlStrategy || 'relative');
        if (strategy === 'absolute') {
            try {
                normalized = new URL(normalized, window.location.origin).href;
            } catch (e) {
            }
        } else if (strategy === 'relative') {
            try {
                var parsed = new URL(normalized, window.location.origin);
                if (parsed.origin === window.location.origin) {
                    normalized = parsed.pathname + parsed.search + parsed.hash;
                }
            } catch (e) {
            }
        }
        return normalized;
    }

    function extractMessageDetails(details) {
        var payload = details;
        var origin = '';
        if (details && typeof details === 'object' && Object.prototype.hasOwnProperty.call(details, 'data')) {
            payload = details.data;
            origin = details.origin || '';
        }
        if (!origin && payload && typeof payload === 'object' && typeof payload.origin === 'string') {
            origin = payload.origin;
        }
        return { payload: payload, origin: origin };
    }

    function buildMeta(payload) {
        var meta = {};
        if (!payload || typeof payload !== 'object') {
            return meta;
        }
        var source = payload;
        if (payload.meta && typeof payload.meta === 'object') {
            source = payload.meta;
        }
        if (typeof source.alt === 'string') {
            meta.alt = source.alt;
        }
        if (typeof source.title === 'string') {
            meta.title = source.title;
        }
        if (typeof source.text === 'string') {
            meta.text = source.text;
        }
        return meta;
    }

    function openLegacyMcpuk(editor, callback, meta) {
        var isImage = meta && meta.filetype === 'image';
        var type = isImage ? 'images' : 'files';
        var title = isImage ? 'Image' : 'File';

        var managerUrl = (window.modx && window.modx.MODX_MANAGER_URL) || window.MODX_MANAGER_URL || '';
        var url = managerUrl + 'media/browser/mcpuk/browse.php?opener=' + opener + '&field=src&type=' + type;

        // Legacy mcpuk uses a global URL handoff; keep it local to this dialog.
        var previousUrl = window.tinymceCallBackURL;
        window.tinymceCallBackURL = '';
        editor.windowManager.open({
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
                var picked = window.tinymceCallBackURL;
                window.tinymceCallBackURL = previousUrl;
                if (picked) {
                    callback(picked, {});
                }
            }
        });
    }

    function openFileManager(editor, callback, meta) {
        if (whichBrowser !== 'efilemanager') {
            openLegacyMcpuk(editor, callback, meta);
            return;
        }

        if (!fileManager || !fileManager.enabled) {
            showMessage(editor, 'File manager is disabled.');
            return;
        }

        var title = (meta && meta.filetype === 'image') ? 'Image' : 'File';
        var url = buildFileManagerUrl(meta);

        editor.windowManager.openUrl({
            title: title,
            url: url,
            buttons: [],
            onMessage: function (api, details) {
                var extracted = extractMessageDetails(details);
                var payload = extracted.payload;
                var origin = extracted.origin;

                if (origin && origin !== window.location.origin) {
                    return;
                }
                if (!payload || typeof payload !== 'object') {
                    return;
                }

                var action = normalizeString(payload.mceAction || payload.action);
                if (action !== 'insert' && action !== 'close') {
                    return;
                }

                if (action === 'close') {
                    api.close();
                    return;
                }

                var content = normalizeString(payload.content || payload.url);
                if (!content) {
                    return;
                }

                var normalized = normalizeSelectedUrl(content);
                if (!normalized) {
                    return;
                }

                callback(normalized, buildMeta(payload));
                api.close();
            }
        });
    }

    if (!window.eTinyMCEFilePicker) {
        window.eTinyMCEFilePicker = function (callback, value, meta) {
            var editor = (this && this.windowManager) ? this : (tinymce && tinymce.activeEditor);
            if (!editor) {
                return;
            }
            openFileManager(editor, callback, meta);
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
            setup: window.eTinyMCESetup,
            license_key: 'gpl'
        };

        var initOptions = Object.assign({}, profileOptions, item.options || {}, baseOptions);
        tinymce.init(initOptions);
    });
})();
