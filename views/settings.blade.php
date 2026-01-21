@php
    $profiles = $profiles ?? [];
    $themes = $themes ?? [];
    $skins = $skins ?? [];
    $currentProfile = $currentProfile ?? '';
    $currentTheme = $currentTheme ?? 'auto';
    $currentSkin = $currentSkin ?? '';

    $selectSkin = '';
    $customSkin = '';
    if ($currentSkin !== '') {
        if (in_array($currentSkin, $skins, true)) {
            $selectSkin = $currentSkin;
        } else {
            $selectSkin = 'custom';
            $customSkin = $currentSkin;
        }
    }
@endphp

<div class="row form-row form-element-select">
    <label for="etinymce_profile" class="control-label col-5 col-md-3 col-lg-2">
        eTinyMCE Profile:
        <small class="form-text text-muted">[(etinymce_profile)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="etinymce_profile" id="etinymce_profile" onchange="documentDirty=true;" size="1">
            @foreach($profiles as $key => $label)
                <option value="{{ $key }}" @if($currentProfile === $key) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="etinymce_editor_theme" class="control-label col-5 col-md-3 col-lg-2">
        Editor Theme:
        <small class="form-text text-muted">[(etinymce_editor_theme)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" name="etinymce_editor_theme" id="etinymce_editor_theme" onchange="documentDirty=true;" size="1">
            @foreach($themes as $theme)
                @if($theme !== 'auto')
                    <option value="{{ $theme }}" @if($currentTheme === $theme) selected @endif>{{ $theme }}</option>
                @endif
            @endforeach
            <option value="auto" @if($currentTheme === 'auto' || $currentTheme === '') selected @endif>auto (manager)</option>
        </select>
        <small class="form-text text-muted">Theme controls skin and content styling. Profile controls toolbar and plugins.</small>
    </div>
</div>

<div class="row form-row form-element-select">
    <label for="etinymce_skin_select" class="control-label col-5 col-md-3 col-lg-2">
        Skin Override:
        <small class="form-text text-muted">[(etinymce_skin)]</small>
    </label>
    <div class="col-7 col-md-9 col-lg-10">
        <select class="form-control" id="etinymce_skin_select" size="1">
            <option value="" @if($selectSkin === '') selected @endif>theme default</option>
            @foreach($skins as $skin)
                <option value="{{ $skin }}" @if($selectSkin === $skin) selected @endif>{{ $skin }}</option>
            @endforeach
            <option value="custom" @if($selectSkin === 'custom') selected @endif>custom...</option>
        </select>
        <input type="text" class="form-control mt-2" id="etinymce_skin_custom" value="{{ $customSkin }}" placeholder="Custom skin" />
        <input type="hidden" name="etinymce_skin" id="etinymce_skin" value="{{ $currentSkin }}" />
        <small class="form-text text-muted">Use only if you need to override the theme-derived skin.</small>
    </div>
</div>

<script>
(function(){
    var select = document.getElementById('etinymce_skin_select');
    var custom = document.getElementById('etinymce_skin_custom');
    var hidden = document.getElementById('etinymce_skin');

    if (!select || !custom || !hidden) {
        return;
    }

    function applyValue() {
        var value = select.value;
        if (value === 'custom') {
            value = custom.value.trim();
        }
        hidden.value = value;
    }

    function toggleCustom() {
        custom.style.display = (select.value === 'custom') ? '' : 'none';
    }

    function onChange() {
        applyValue();
        toggleCustom();
        if (typeof documentDirty !== 'undefined') {
            documentDirty = true;
        }
    }

    select.addEventListener('change', onChange);
    custom.addEventListener('input', onChange);

    toggleCustom();
    applyValue();
})();
</script>
