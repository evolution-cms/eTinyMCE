<?php

if (!class_exists('League\\Flysystem\\Adapter\\Local', false)) {
    if (class_exists('League\\Flysystem\\Local\\LocalFilesystemAdapter')) {
        class_alias('League\\Flysystem\\Local\\LocalFilesystemAdapter', 'League\\Flysystem\\Adapter\\Local');
    }
}
