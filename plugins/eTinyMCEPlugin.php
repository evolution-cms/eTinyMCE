<?php

if (!function_exists('eTinyMCE_log')) {
    function eTinyMCE_log(string $message, int $type = 2): void
    {
        if (function_exists('evo')) {
            evo()->logEvent(0, $type, $message, 'eTinyMCE');
        }
    }
}

if (!function_exists('eTinyMCE_getManagerThemeMode')) {
    function eTinyMCE_getManagerThemeMode(): ?string
    {
        $themeModes = ['', 'lightness', 'light', 'dark', 'darkness'];

        if (isset($_COOKIE['MODX_themeMode'])) {
            $index = (int)$_COOKIE['MODX_themeMode'];
            if (!empty($themeModes[$index])) {
                return $themeModes[$index];
            }
        }

        $configMode = (int)evo()->getConfig('manager_theme_mode');
        if (!empty($themeModes[$configMode])) {
            return $themeModes[$configMode];
        }

        return null;
    }
}

if (!function_exists('eTinyMCE_isValidSelector')) {
    function eTinyMCE_isValidSelector(string $selector): bool
    {
        return trim($selector) !== '';
    }
}

if (!function_exists('eTinyMCE_normalizeSelector')) {
    function eTinyMCE_normalizeSelector(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return $selector;
        }

        $first = $selector[0] ?? '';
        if ($first === '#' || $first === '.' || $first === '[') {
            return $selector;
        }

        if (strpos($selector, ' ') !== false || strpos($selector, ':') !== false) {
            return $selector;
        }

        return '#' . $selector;
    }
}

if (!function_exists('eTinyMCE_themeCssUrls')) {
    function eTinyMCE_themeCssUrls($value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, function ($item) {
                return is_string($item) && $item !== '';
            }));
        }

        return [];
    }
}

Event::listen('evolution.OnRichTextEditorRegister', function () {
    return 'eTinyMCE';
});

Event::listen('evolution.OnInterfaceSettingsRender', function () {
    $settings = config('seiger.settings.eTinyMCE', []);
    $profiles = $settings['profiles'] ?? [];
    $themes = $settings['themes'] ?? [];

    $profileOptions = [];
    foreach ($profiles as $key => $profile) {
        $label = is_array($profile) && isset($profile['label']) ? $profile['label'] : $key;
        $profileOptions[$key] = $label;
    }

    $themeOptions = array_keys($themes);
    $themeOptions[] = 'auto';

    $skinOptions = ['oxide', 'oxide-dark'];

    $currentProfile = evo()->getConfig('etinymce_profile') ?: ($settings['default_profile'] ?? 'full');
    $currentTheme = evo()->getConfig('etinymce_editor_theme') ?: 'auto';
    $currentSkin = evo()->getConfig('etinymce_skin') ?: '';

    return 
        \View::make('eTinyMCE::settings', [
            'profiles' => $profileOptions,
            'themes' => $themeOptions,
            'skins' => $skinOptions,
            'currentProfile' => $currentProfile,
            'currentTheme' => $currentTheme,
            'currentSkin' => $currentSkin,
        ])->toHtml();
});

Event::listen('evolution.OnRichTextEditorInit', function ($params) {
    if (!isset($params['editor']) || $params['editor'] !== 'eTinyMCE') {
        return '';
    }

    $settings = config('seiger.settings.eTinyMCE', []);
    $profiles = $settings['profiles'] ?? [];
    $themes = $settings['themes'] ?? [];

    $defaultProfile = $settings['default_profile'] ?? 'full';
    $defaultSkin = $settings['default_skin'] ?? 'oxide';
    $defaultOpener = $settings['opener'] ?? 'tinymce';

    $systemProfile = evo()->getConfig('etinymce_profile');
    if (!$systemProfile || !isset($profiles[$systemProfile])) {
        if ($systemProfile && !isset($profiles[$systemProfile])) {
            eTinyMCE_log('Unknown etinymce_profile: ' . $systemProfile);
        }
        $systemProfile = $defaultProfile;
    }

    $systemEditorTheme = evo()->getConfig('etinymce_editor_theme');
    $systemSkin = evo()->getConfig('etinymce_skin');

    $opener = evo()->getConfig('etinymce_opener') ?: $defaultOpener;
    $allowedOpeners = ['tinymce', 'tinymce6', 'tinymce5', 'tinymce4'];
    if (!in_array($opener, $allowedOpeners, true)) {
        eTinyMCE_log('Invalid etinymce_opener: ' . $opener);
        $opener = $defaultOpener;
    }

    $baseUrl = MODX_SITE_URL . 'assets/plugins/eTinyMCE';
    $tinymceJs = $baseUrl . '/tinymce/tinymce.min.js';
    $tinymceJsPath = MODX_BASE_PATH . 'assets/plugins/eTinyMCE/tinymce/tinymce.min.js';

    if (!is_file($tinymceJsPath)) {
        eTinyMCE_log('Missing TinyMCE assets. Run vendor:publish for eTinyMCE.');
        return '<script>console.warn("eTinyMCE assets are not published.");</script>' .
            '<script>document.addEventListener("DOMContentLoaded",function(){var el=document.querySelector("#main")||document.body;if(el){var d=document.createElement("div");d.className="alert alert-danger";d.textContent="eTinyMCE assets are not published. Run vendor:publish.";el.prepend(d);}});</script>';
    }

    $elements = $params['elements'] ?? [];
    if (!is_array($elements) || $elements === []) {
        return '';
    }

    $optionsByField = $params['options'] ?? [];
    $protected = ['selector', 'target', 'file_picker_callback', 'setup', 'init_instance_callback', 'plugins'];

    $groups = [];
    foreach ($elements as $element) {
        $selector = is_string($element) ? $element : '';
        if (!eTinyMCE_isValidSelector($selector)) {
            eTinyMCE_log('Invalid editor selector: ' . $selector);
            continue;
        }

        $selector = eTinyMCE_normalizeSelector($selector);

        $fieldOptions = $optionsByField[$element] ?? [];
        if (!is_array($fieldOptions)) {
            $fieldOptions = [];
        }

        $profile = $fieldOptions['profile'] ?? $fieldOptions['theme'] ?? $systemProfile;
        if (!isset($profiles[$profile])) {
            eTinyMCE_log('Unknown profile: ' . $profile . '. Using default.');
            $profile = $defaultProfile;
        }

        $editorThemeOverride = $fieldOptions['editor_theme'] ?? null;
        $groupTheme = $editorThemeOverride ?: 'auto';

        if (isset($fieldOptions['plugins'])) {
            eTinyMCE_log('Ignoring plugins override for field: ' . $element);
        }

        $fieldOverrides = array_diff_key($fieldOptions, array_flip($protected));
        unset($fieldOverrides['profile'], $fieldOverrides['theme'], $fieldOverrides['editor_theme']);

        $overrideKey = $fieldOverrides;
        ksort($overrideKey);
        $groupKey = $profile . '|' . $groupTheme . '|' . md5(json_encode($overrideKey));

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'profile' => $profile,
                'editor_theme_override' => $editorThemeOverride,
                'selectors' => [],
                'field_overrides' => $fieldOverrides,
            ];
        }

        $groups[$groupKey]['selectors'][] = $selector;
    }

    if ($groups === []) {
        return '';
    }

    $configDirPath = MODX_BASE_PATH . 'assets/plugins/eTinyMCE/configs';
    $defaultConfigPath = $configDirPath . '/' . $defaultProfile . '.js';
    if (!is_file($defaultConfigPath)) {
        eTinyMCE_log('Missing default profile config: ' . $defaultProfile);
    }

    $profileScripts = [$defaultProfile];
    foreach ($groups as $group) {
        $profileScripts[] = $group['profile'];
    }
    $profileScripts = array_values(array_unique($profileScripts));

    $output = [];
    $output[] = '<script src="' . $tinymceJs . '"></script>';
    $output[] = '<script>window.eTinyMCEProfiles = window.eTinyMCEProfiles || {};</script>';

    $initQueue = [];
    foreach ($profileScripts as $profile) {
        $configPath = $configDirPath . '/' . $profile . '.js';
        if (!is_file($configPath)) {
            eTinyMCE_log('Missing profile config: ' . $profile . '.js');
            continue;
        }

        $mtime = @filemtime($configPath);
        $version = is_int($mtime) ? ('?v=' . $mtime) : '';
        if (!is_int($mtime)) {
            eTinyMCE_log('filemtime unavailable for profile config: ' . $profile . '.js');
        }

        $output[] = '<script src="' . $baseUrl . '/configs/' . $profile . '.js' . $version . '"></script>';
    }

    $managerTheme = eTinyMCE_getManagerThemeMode();

    foreach ($groups as $group) {
        $profile = $group['profile'];
        $selectors = $group['selectors'];
        if ($selectors === []) {
            continue;
        }

        $editorTheme = $group['editor_theme_override'] ?: $systemEditorTheme;
        if (!$editorTheme || $editorTheme === 'auto') {
            $editorTheme = $managerTheme ?: 'light';
        }

        if (!isset($themes[$editorTheme])) {
            eTinyMCE_log('Unknown editor_theme: ' . $editorTheme . '. Using light.');
            $editorTheme = 'light';
        }

        $fieldOverrides = $group['field_overrides'];

        $fieldSkin = $fieldOverrides['skin'] ?? null;
        $fieldContentCss = $fieldOverrides['content_css'] ?? null;
        unset($fieldOverrides['skin'], $fieldOverrides['content_css']);

        $skin = $fieldSkin ?: ($systemSkin ?: ($themes[$editorTheme]['skin'] ?? $defaultSkin));
        $skinPath = MODX_BASE_PATH . 'assets/plugins/eTinyMCE/tinymce/skins/ui/' . $skin . '/skin.min.css';
        if (!is_file($skinPath)) {
            eTinyMCE_log('Missing skin assets: ' . $skin);
            $skin = $defaultSkin;
        }
        $defaultSkinPath = MODX_BASE_PATH . 'assets/plugins/eTinyMCE/tinymce/skins/ui/' . $defaultSkin . '/skin.min.css';
        if ($skin === $defaultSkin && !is_file($defaultSkinPath)) {
            eTinyMCE_log('Missing default skin assets: ' . $defaultSkin);
        }

        $contentCss = evo()->getConfig('editor_css_path') ?: '';
        $themeCss = eTinyMCE_themeCssUrls($themes[$editorTheme]['content_css'] ?? '');
        $resolvedThemeCss = [];

        foreach ($themeCss as $cssPath) {
            if (preg_match('/^https?:\/\//', $cssPath) || strpos($cssPath, '//') === 0) {
                $resolvedThemeCss[] = $cssPath;
                continue;
            }

            $path = $cssPath;
            if ($cssPath[0] === '/') {
                $path = MODX_BASE_PATH . ltrim($cssPath, '/');
            }

            if (is_file($path)) {
                $resolvedThemeCss[] = $cssPath;
            } else {
                eTinyMCE_log('Missing theme content_css: ' . $cssPath);
            }
        }

        $contentCssList = [];
        if ($contentCss !== '') {
            $contentCssList[] = $contentCss;
        }
        foreach ($resolvedThemeCss as $cssUrl) {
            $contentCssList[] = $cssUrl;
        }

        $computedContentCss = $contentCssList;
        if ($fieldContentCss !== null) {
            $computedContentCss = $fieldContentCss;
        }

        $profileOptions = $profiles[$profile]['options'] ?? [];
        $systemOverrides = [];
        if ($systemSkin) {
            $systemOverrides['skin'] = $systemSkin;
        }

        $mergedOptions = array_replace_recursive($profileOptions, $systemOverrides, $fieldOverrides);

        $language = evo()->getConfig('fe_editor_lang') ?: '';
        $languageUrl = '';
        if ($language !== '') {
            $languagePath = MODX_BASE_PATH . 'assets/plugins/eTinyMCE/tinymce/langs/' . $language . '.js';
            if (is_file($languagePath)) {
                $languageUrl = $baseUrl . '/tinymce/langs/' . $language . '.js';
            } else {
                $language = '';
            }
        }

        if ($language === '') {
            $fallbackPath = MODX_BASE_PATH . 'assets/plugins/eTinyMCE/tinymce/langs/en.js';
            if (is_file($fallbackPath)) {
                $language = 'en';
                $languageUrl = $baseUrl . '/tinymce/langs/en.js';
            }
        }

        $selectorsList = implode(',', $selectors);

        $initOptions = $mergedOptions;
        $initOptions['skin'] = $skin;
        if ($computedContentCss !== [] && $computedContentCss !== '') {
            $initOptions['content_css'] = $computedContentCss;
        }
        if ($language !== '') {
            $initOptions['language'] = $language;
            if ($languageUrl !== '') {
                $initOptions['language_url'] = $languageUrl;
            }
        }

        $initOptionsJson = json_encode($initOptions, JSON_UNESCAPED_SLASHES);
        if ($initOptionsJson === false) {
            eTinyMCE_log('Failed to encode init options for profile: ' . $profile);
            continue;
        }

        $initQueue[] = [
            'profile' => $profile,
            'selectors' => $selectorsList,
            'options' => $initOptions,
        ];
    }

    $queueJson = json_encode($initQueue, JSON_UNESCAPED_SLASHES);
    if ($queueJson === false) {
        eTinyMCE_log('Failed to encode init queue.');
        return implode("\n", $output);
    }

    $configJson = json_encode([
        'queue' => $initQueue,
        'defaultProfile' => $defaultProfile,
        'opener' => $opener,
    ], JSON_UNESCAPED_SLASHES);

    if ($configJson === false) {
        eTinyMCE_log('Failed to encode init config.');
        return implode("\n", $output);
    }

    $output[] = '<script>window.eTinyMCEConfig=' . $configJson . ';</script>';
    $output[] = '<script src="' . $baseUrl . '/js/etinymce-init.js"></script>';

    return implode("\n", $output);
});
