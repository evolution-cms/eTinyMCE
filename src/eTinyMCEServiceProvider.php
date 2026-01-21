<?php namespace EvolutionCMS\eTinyMCE;

use EvolutionCMS\ServiceProvider;

class eTinyMCEServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/eTinyMCECheck.php', 'cms.settings');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'eTinyMCE');
        if ($this->app->runningInConsole()) {
            $this->publishResources();
        }
    }

    public function register()
    {
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    protected function publishResources(): void
    {
        $this->publishes([
            dirname(__DIR__) . '/config/eTinyMCESettings.php' => config_path('cms/settings/eTinyMCE.php', true),
        ], 'etinymce-config');

        $this->publishes([
            dirname(__DIR__) . '/config/which_editor.php' => config_path('cms/settings/which_editor.php', true),
        ], 'etinymce-config');

        $tinymcePath = function_exists('base_path')
            ? base_path('vendor/tinymce/tinymce')
            : dirname(__DIR__, 3) . '/tinymce/tinymce';

        $tinymceFiles = $this->collectPublishFiles($tinymcePath, public_path('assets/plugins/eTinyMCE/tinymce'));
        if ($tinymceFiles !== []) {
            $this->publishes($tinymceFiles, 'etinymce-assets');
        }

        $profileFiles = $this->collectPublishFiles(
            dirname(__DIR__) . '/public/configs',
            public_path('assets/plugins/eTinyMCE/configs')
        );
        if ($profileFiles !== []) {
            $this->publishes($profileFiles, 'etinymce-profiles');
        }

        $jsFiles = $this->collectPublishFiles(
            dirname(__DIR__) . '/public/js',
            public_path('assets/plugins/eTinyMCE/js')
        );
        if ($jsFiles !== []) {
            $this->publishes($jsFiles, 'etinymce-assets');
        }
    }

    protected function collectPublishFiles(string $sourceDir, string $targetDir): array
    {
        if (!is_dir($sourceDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $relative = substr($path, strlen($sourceDir) + 1);
            $files[$path] = $targetDir . DIRECTORY_SEPARATOR . $relative;
        }

        return $files;
    }
}
