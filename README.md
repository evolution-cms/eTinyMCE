# eTinyMCE

TinyMCE 8 integration for Evolution CMS 3.5.2+.

## Requirements
- PHP 8.3+
- Evolution CMS 3.5.2+
- Composer 2.2+

## Install
From the `core` directory:

php artisan package:installrequire evolution-cms/etinymce "*"

## Publish assets and config
Publish everything:

php artisan vendor:publish --provider="EvolutionCMS\eTinyMCE\eTinyMCEServiceProvider"

Or publish by tag:

php artisan vendor:publish --provider="EvolutionCMS\eTinyMCE\eTinyMCEServiceProvider" --tag=etinymce-config
php artisan vendor:publish --provider="EvolutionCMS\eTinyMCE\eTinyMCEServiceProvider" --tag=etinymce-assets
php artisan vendor:publish --provider="EvolutionCMS\eTinyMCE\eTinyMCEServiceProvider" --tag=etinymce-profiles

Note: eTinyMCE publishes assets as individual files to avoid Evo directory publish issues.
The first publish may take a bit longer because TinyMCE has many files.

## Configuration
Published config path:
- core/custom/config/seiger/settings/eTinyMCE.php

Default profiles and theme mapping live here. Example structure:
- default_profile
- default_skin
- opener (tinymce)
- themes (light/lightness/dark/darkness)
- profiles (full/mini/introtext/custom)

## Manager settings
System Settings > Interface:
- Profile (required)
- Editor theme (light/lightness/dark/darkness or auto)
- Skin override (optional)

Editor theme controls skin. Profile controls toolbar/plugins.

## Profiles
JS profile files live at:
- public/assets/plugins/eTinyMCE/configs/<profile>.js

Add a new profile by:
1) Creating a new JS config file in the path above.
2) Adding the profile to core/custom/config/seiger/settings/eTinyMCE.php.

Profiles contain editor options only. Do not set skin or content_css in profiles.

## File manager
Uses the standard Evo file manager (mcpuk) with opener `tinymce` by default.
You can override the opener via system setting `etinymce_opener`.

## TinyMCE license key
TinyMCE 8 requires an explicit license declaration even for self-hosted Community build.
eTinyMCE always sets `license_key = 'gpl'` internally.

## Troubleshooting
- If TinyMCE does not load, ensure assets are published to public/assets/plugins/eTinyMCE.
- If a profile config is missing, the editor falls back to the default profile.
