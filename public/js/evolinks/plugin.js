(function () {
    if (typeof tinymce === 'undefined') {
        return;
    }

    tinymce.addI18n('en', {
        'Search in EVO': 'Search in EVO',
        'Browse': 'Browse',
        'Results': 'Results'
    });
    tinymce.addI18n('de', {
        'Search in EVO': 'In EVO suchen',
        'Browse': 'Durchsuchen',
        'Results': 'Ergebnisse'
    });
    tinymce.addI18n('uk', {
        'Search in EVO': '\u041f\u043e\u0448\u0443\u043a \u0432 EVO',
        'Browse': '\u041e\u0433\u043b\u044f\u0434',
        'Results': '\u0420\u0435\u0437\u0443\u043b\u044c\u0442\u0430\u0442\u0438'
    });
    tinymce.addI18n('pl', {
        'Search in EVO': 'Szukaj w EVO',
        'Browse': 'Przegl\u0105daj',
        'Results': 'Wyniki'
    });
    tinymce.addI18n('ja', {
        'Search in EVO': 'EVO\u3067\u691c\u7d22',
        'Browse': '\u53c2\u7167',
        'Results': '\u7d50\u679c'
    });
    tinymce.addI18n('jp', {
        'Search in EVO': 'EVO\u3067\u691c\u7d22',
        'Browse': '\u53c2\u7167',
        'Results': '\u7d50\u679c'
    });

    function assign(target) {
        var i;
        for (i = 1; i < arguments.length; i += 1) {
            var source = arguments[i];
            if (!source) {
                continue;
            }
            Object.keys(source).forEach(function (key) {
                target[key] = source[key];
            });
        }
        return target;
    }

    function normalizeBool(value, fallback) {
        if (typeof value === 'boolean') {
            return value;
        }
        if (typeof value === 'string') {
            return value === '1' || value.toLowerCase() === 'true';
        }
        return fallback;
    }

    function normalizeInt(value, fallback) {
        var num = parseInt(value, 10);
        return isNaN(num) ? fallback : num;
    }

    function buildBaseUrl(base) {
        var url = (base || '').toString().replace(/\/+$/, '');
        if (url && url[0] !== '/') {
            url = '/' + url;
        }
        return url;
    }

    function ensureLeadingSlash(value) {
        if (!value) {
            return '';
        }
        if (value[0] !== '/') {
            return '/' + value;
        }
        return value;
    }

    tinymce.PluginManager.add('evolinks', function (editor) {
        var globalCfg = window.eTinyMCEConfig || {};
        var baseUrl = buildBaseUrl(globalCfg.baseUrl || '');

        var defaults = {
            searchUrl: baseUrl + '/assets/plugins/eTinyMCE/connectors/evolinks-search.php',
            minChars: 2,
            debounce: 250,
            limit: 10,
            outputMode: 'placeholder',
            includeUnpublished: false,
            enableTree: true,
            cacheSize: 20
        };

        var rawSettings = editor.getParam('evolinks', {});
        var settings = assign({}, defaults, rawSettings);

        settings.minChars = normalizeInt(settings.minChars, defaults.minChars);
        settings.debounce = normalizeInt(settings.debounce, defaults.debounce);
        settings.limit = normalizeInt(settings.limit, defaults.limit);
        settings.cacheSize = normalizeInt(settings.cacheSize, defaults.cacheSize);
        settings.includeUnpublished = normalizeBool(settings.includeUnpublished, defaults.includeUnpublished);
        settings.enableTree = normalizeBool(settings.enableTree, defaults.enableTree);
        if (!settings.searchUrl) {
            settings.searchUrl = defaults.searchUrl;
        }

        function t(key) {
            if (editor && typeof editor.translate === 'function') {
                return editor.translate(key);
            }
            return key;
        }

        function showNotice(text, type) {
            if (editor && editor.notificationManager && typeof editor.notificationManager.open === 'function') {
                editor.notificationManager.open({ text: text, type: type || 'warning', timeout: 3000 });
            }
        }

        function buildLinkAttrs(data) {
            return {
                href: data.href || null,
                target: data.target || null,
                rel: data.rel || null,
                'class': data['class'] || null,
                title: data.title || null
            };
        }

        function isOnlyTextSelected(anchorElm) {
            var html = editor.selection.getContent();
            if (/<\/?.+?>/.test(html) && (!/^<a [^>]+>[^<]+<\/a>$/.test(html) || html.indexOf('href=') === -1)) {
                return false;
            }

            if (anchorElm) {
                var nodes = anchorElm.childNodes;
                var i;
                if (!nodes || nodes.length === 0) {
                    return false;
                }
                for (i = nodes.length - 1; i >= 0; i -= 1) {
                    if (nodes[i].nodeType !== 3) {
                        return false;
                    }
                }
            }

            return true;
        }

        function buildAnchorList(currentHref) {
            var list = [];
            if (Array.isArray(editor.settings.anchor_list)) {
                list = editor.settings.anchor_list.slice();
            } else {
                editor.dom.select('a:not([href])').forEach(function (anchor) {
                    var id = anchor.name || anchor.id;
                    if (id) {
                        list.push({ text: id, value: '#' + id });
                    }
                });
            }
            if (list.length) {
                list.unshift({ text: 'None', value: '' });
                if (currentHref) {
                    list = list.map(function (item) {
                        if (!item.value) {
                            return item;
                        }
                        return assign({}, item, { selected: currentHref.indexOf(item.value) !== -1 });
                    });
                }
            }
            return list.length ? list : null;
        }

        function buildListItems(list, output) {
            var out = output || [];
            if (!Array.isArray(list)) {
                return out;
            }
            list.forEach(function (item) {
                if (!item) {
                    return;
                }
                if (Array.isArray(item.menu)) {
                    buildListItems(item.menu, out);
                    return;
                }
                var value = item.value || item.url || '';
                out.push({ text: item.text || item.title || value, value: value });
            });
            return out;
        }

        function resolveLinkList() {
            return new Promise(function (resolve) {
                var linkList = editor.settings.link_list;
                if (!linkList) {
                    resolve(null);
                    return;
                }
                if (typeof linkList === 'string') {
                    tinymce.util.XHR.send({
                        url: linkList,
                        success: function (text) {
                            try {
                                resolve(buildListItems(JSON.parse(text), [{ text: 'None', value: '' }]));
                            } catch (e) {
                                resolve(null);
                            }
                        },
                        error: function () {
                            resolve(null);
                        }
                    });
                    return;
                }
                if (typeof linkList === 'function') {
                    linkList(function (items) {
                        resolve(buildListItems(items, [{ text: 'None', value: '' }]));
                    });
                    return;
                }
                resolve(buildListItems(linkList, [{ text: 'None', value: '' }]));
            });
        }

        function buildTargetList() {
            var list = editor.settings.target_list;
            if (list === false) {
                return null;
            }
            if (!list) {
                list = [
                    { text: 'None', value: '' },
                    { text: 'New window', value: '_blank' }
                ];
            }
            return buildListItems(list);
        }

        function buildRelList() {
            if (!editor.settings.rel_list) {
                return null;
            }
            return buildListItems(editor.settings.rel_list);
        }

        function buildClassList() {
            if (!editor.settings.link_class_list) {
                return null;
            }
            return buildListItems(editor.settings.link_class_list);
        }

        function buildHrefFromResult(item) {
            if (!item) {
                return '';
            }
            if (settings.outputMode === 'relative') {
                if (item.uri) {
                    return ensureLeadingSlash(item.uri);
                }
                showNotice('Missing URI for selected resource. Falling back to placeholder.');
            } else if (settings.outputMode === 'absolute') {
                if (item.url) {
                    return item.url;
                }
                showNotice('Missing URL for selected resource. Falling back to placeholder.');
            }
            return '[~' + item.id + '~]';
        }

        function fetchSearch(query, cache) {
            return new Promise(function (resolve) {
                if (cache && cache[query]) {
                    resolve(cache[query]);
                    return;
                }

                var url = settings.searchUrl || '';
                if (!url) {
                    resolve([]);
                    return;
                }

                var params = ['q=' + encodeURIComponent(query), 'limit=' + encodeURIComponent(settings.limit)];
                if (settings.includeUnpublished) {
                    params.push('includeUnpublished=1');
                }
                url += (url.indexOf('?') === -1 ? '?' : '&') + params.join('&');

                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) {
                        return;
                    }
                    if (xhr.status < 200 || xhr.status >= 300) {
                        resolve([]);
                        return;
                    }
                    try {
                        var data = JSON.parse(xhr.responseText) || [];
                        resolve(Array.isArray(data) ? data : []);
                    } catch (e) {
                        resolve([]);
                    }
                };
                xhr.send();
            });
        }

        function openDialog() {
            var dom = editor.dom;
            var selection = editor.selection;
            var selectedElm = selection.getNode();
            var anchorElm = dom.getParent(selectedElm, 'a[href]');
            var onlyText = isOnlyTextSelected(anchorElm);

            var initialText = anchorElm ? (anchorElm.innerText || anchorElm.textContent) : selection.getContent({ format: 'text' });
            var initialHref = anchorElm ? dom.getAttrib(anchorElm, 'href') : '';
            var initialData = {
                href: initialHref || '',
                text: initialText || '',
                title: anchorElm ? dom.getAttrib(anchorElm, 'title') : '',
                target: anchorElm ? dom.getAttrib(anchorElm, 'target') : (editor.settings.default_link_target || ''),
                rel: anchorElm ? dom.getAttrib(anchorElm, 'rel') : '',
                'class': anchorElm ? dom.getAttrib(anchorElm, 'class') : ''
            };

            var anchorList = buildAnchorList(initialHref);

            var state = {
                searchItems: [],
                searchMap: {},
                cache: {},
                cacheOrder: [],
                updating: false,
                timer: null,
                linkList: null,
                relList: buildRelList(),
                targetList: buildTargetList(),
                classList: buildClassList(),
                anchorList: anchorList
            };

            function updateCache(key, value) {
                if (!key) {
                    return;
                }
                if (!state.cache[key]) {
                    state.cacheOrder.push(key);
                }
                state.cache[key] = value;
                if (state.cacheOrder.length > settings.cacheSize) {
                    var oldest = state.cacheOrder.shift();
                    if (oldest) {
                        delete state.cache[oldest];
                    }
                }
            }

            function buildItems() {
                var items = [];
                items.push({
                    type: 'input',
                    name: 'href',
                    label: t('Url')
                });
                items.push({
                    type: 'button',
                    name: 'browse',
                    text: t('Browse')
                });
                items.push({
                    type: 'input',
                    name: 'search',
                    label: t('Search in EVO')
                });
                items.push({
                    type: 'selectbox',
                    name: 'search_result',
                    label: t('Results'),
                    items: state.searchItems,
                    enabled: state.searchItems.length > 0
                });
                if (onlyText) {
                    items.push({
                        type: 'input',
                        name: 'text',
                        label: t('Text to display')
                    });
                }
                if (editor.settings.link_title !== false) {
                    items.push({
                        type: 'input',
                        name: 'title',
                        label: t('Title')
                    });
                }
                if (state.anchorList && state.anchorList.length) {
                    items.push({
                        type: 'selectbox',
                        name: 'anchor',
                        label: t('Anchors'),
                        items: state.anchorList
                    });
                }
                if (state.linkList && state.linkList.length) {
                    items.push({
                        type: 'selectbox',
                        name: 'linklist',
                        label: t('Link list'),
                        items: state.linkList
                    });
                }
                if (state.relList && state.relList.length) {
                    items.push({
                        type: 'selectbox',
                        name: 'rel',
                        label: t('Rel'),
                        items: state.relList
                    });
                }
                if (state.targetList && state.targetList.length) {
                    items.push({
                        type: 'selectbox',
                        name: 'target',
                        label: t('Target'),
                        items: state.targetList
                    });
                }
                if (state.classList && state.classList.length) {
                    items.push({
                        type: 'selectbox',
                        name: 'class',
                        label: t('Class'),
                        items: state.classList
                    });
                }
                return items;
            }

            function buildDialogSpec(data) {
                return {
                    title: t('Insert link'),
                    size: 'normal',
                    body: {
                        type: 'panel',
                        items: buildItems()
                    },
                    initialData: data,
                    buttons: [
                        { type: 'cancel', text: t('Cancel') },
                        { type: 'submit', text: t('Save'), primary: true }
                    ],
                    onChange: function (api, details) {
                        if (state.updating) {
                            return;
                        }
                        if (details.name === 'search') {
                            var value = (details.value || '').toString().trim();
                            if (state.timer) {
                                clearTimeout(state.timer);
                            }
                            if (value.length < settings.minChars) {
                                state.searchItems = [];
                                state.searchMap = {};
                                state.updating = true;
                                api.redial(buildDialogSpec(api.getData()));
                                state.updating = false;
                                return;
                            }
                            state.timer = setTimeout(function () {
                                fetchSearch(value, state.cache).then(function (results) {
                                    var items = [];
                                    var map = {};
                                    results.forEach(function (item) {
                                        if (!item || typeof item.id === 'undefined') {
                                            return;
                                        }
                                        var label = (item.pagetitle || item.title || '') + ' (' + item.id + ')';
                                        items.push({ text: label, value: String(item.id) });
                                        map[String(item.id)] = item;
                                    });
                                    state.searchItems = items;
                                    state.searchMap = map;
                                    updateCache(value, results);
                                    state.updating = true;
                                    api.redial(buildDialogSpec(api.getData()));
                                    state.updating = false;
                                });
                            }, settings.debounce);
                        }

                        if (details.name === 'search_result') {
                            var selected = state.searchMap[String(details.value)];
                            if (selected) {
                                var next = api.getData();
                                next.href = buildHrefFromResult(selected);
                                if (!next.text && selected.title) {
                                    next.text = selected.title;
                                }
                                state.updating = true;
                                api.setData(next);
                                state.updating = false;
                            }
                        }

                        if (details.name === 'linklist') {
                            var data = api.getData();
                            if (details.value) {
                                data.href = details.value;
                                if (!data.text) {
                                    data.text = details.value;
                                }
                                state.updating = true;
                                api.setData(data);
                                state.updating = false;
                            }
                        }

                        if (details.name === 'anchor') {
                            var anchorData = api.getData();
                            anchorData.href = details.value || '';
                            state.updating = true;
                            api.setData(anchorData);
                            state.updating = false;
                        }
                    },
                    onAction: function (api, details) {
                        if (details.name !== 'browse') {
                            return;
                        }
                        var picker = editor.settings.file_picker_callback;
                        if (typeof picker !== 'function') {
                            showNotice('File picker is not available.');
                            return;
                        }
                        picker(function (value) {
                            var data = api.getData();
                            data.href = value;
                            state.updating = true;
                            api.setData(data);
                            state.updating = false;
                        }, api.getData().href || '', { filetype: 'file' });
                    },
                    onSubmit: function (api) {
                        var data = api.getData();
                        var href = (data.href || '').toString().trim();

                        if (!href) {
                            editor.execCommand('unlink');
                            api.close();
                            return;
                        }

                        function insertLink(finalHref) {
                            data.href = finalHref;
                            var linkAttrs = buildLinkAttrs(data);
                            if (anchorElm) {
                                editor.focus();
                                if (onlyText && data.text !== initialText) {
                                    if ('innerText' in anchorElm) {
                                        anchorElm.innerText = data.text;
                                    } else {
                                        anchorElm.textContent = data.text;
                                    }
                                }
                                dom.setAttribs(anchorElm, linkAttrs);
                                selection.select(anchorElm);
                                editor.undoManager.add();
                            } else {
                                if (onlyText) {
                                    editor.insertContent(dom.createHTML('a', linkAttrs, dom.encode(data.text || finalHref)));
                                } else {
                                    editor.execCommand('mceInsertLink', false, linkAttrs);
                                }
                            }
                            api.close();
                        }

                        function delayedConfirm(message, callback) {
                            var rng = editor.selection.getRng();
                            window.setTimeout(function () {
                                editor.windowManager.confirm(message, function (state) {
                                    editor.selection.setRng(rng);
                                    callback(state);
                                });
                            }, 0);
                        }

                        if (href.indexOf('@') > 0 && href.indexOf('//') === -1 && href.indexOf('mailto:') === -1) {
                            delayedConfirm(
                                t('The URL you entered seems to be an email address. Do you want to add the required mailto: prefix?'),
                                function (state) {
                                    insertLink(state ? 'mailto:' + href : href);
                                }
                            );
                            return;
                        }

                        if ((editor.settings.link_assume_external_targets && !/^\w+:/i.test(href)) ||
                            (!editor.settings.link_assume_external_targets && /^\s*www\./i.test(href))) {
                            delayedConfirm(
                                t('The URL you entered seems to be an external link. Do you want to add the required http:// prefix?'),
                                function (state) {
                                    insertLink(state ? 'http://' + href : href);
                                }
                            );
                            return;
                        }

                        insertLink(href);
                    },
                    onClose: function () {
                        if (state.watcher) {
                            clearInterval(state.watcher);
                            state.watcher = null;
                        }
                    }
                };
            }

            function openWithLists(linkList) {
                state.linkList = linkList;
                var dialogApi = editor.windowManager.open(buildDialogSpec(initialData));

                if (settings.enableTree && window.parent && window.parent.modx && window.parent.modx.tree) {
                    var lastId = window.parent.modx.tree.itemToChange;
                    state.watcher = setInterval(function () {
                        var currentId = window.parent.modx.tree.itemToChange;
                        if (currentId && currentId !== lastId) {
                            lastId = currentId;
                            var title = window.parent.modx.tree.selectedObjectName || '';
                            var data = dialogApi.getData();
                            data.href = buildHrefFromResult({ id: currentId, title: title });
                            if (!data.text && title) {
                                data.text = title;
                            }
                            state.updating = true;
                            dialogApi.setData(data);
                            state.updating = false;
                        }
                    }, 250);
                }
            }

            resolveLinkList().then(openWithLists);
        }

        function registerCommand() {
            editor.addCommand('mceLink', openDialog);
        }

        registerCommand();
        editor.on('init', registerCommand);
    });
})();
