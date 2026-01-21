<?php namespace EvolutionCMS\eTinyMCE;

use EvolutionCMS\ServiceProvider;

class eTinyMCEServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/eTinyMCECheck.php', 'cms.settings');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'eTinyMCE');
        $this->publishResources();
    }

    public function register()
    {
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    protected function publishResources(): void
    {
        $this->publishes([
            dirname(__DIR__) . '/config/eTinyMCESettings.php' => config_path('seiger/settings/eTinyMCE.php', true),
        ], 'etinymce-config');

        $tinymcePath = function_exists('base_path')
            ? base_path('vendor/tinymce/tinymce')
            : dirname(__DIR__, 3) . '/tinymce/tinymce';

        $this->publishes([
            $tinymcePath => public_path('assets/plugins/eTinyMCE/tinymce'),
        ], 'etinymce-assets');

        $this->publishes([
            dirname(__DIR__) . '/public/configs' => public_path('assets/plugins/eTinyMCE/configs'),
        ], 'etinymce-profiles');
    }
}
