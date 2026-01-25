<?php

use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Facades\Route;

Route::middleware(config('app.middleware.global', []))->get('evo-link-search', function () {
    if (!isset($_SESSION['mgrValidated'])) {
        return response()->json([], 403);
    }

    $evo = evo();
    if (empty($evo->config)) {
        $evo->getSettings();
    }

    $query = trim((string)request('q', ''));
    if ($query === '') {
        return response()->json([]);
    }

    $limit = (int)request('limit', 10);
    if ($limit <= 0) {
        $limit = 10;
    }
    if ($limit > 50) {
        $limit = 50;
    }

    $includeUnpublished = request('includeUnpublished') === '1';
    $includeHidden = request('includeHidden') === '1';

    $like = '%' . $query . '%';
    $builder = SiteContent::query()
        ->select(['id', 'pagetitle', 'alias'])
        ->where('deleted', 0)
        ->where(function ($sub) use ($like) {
            $sub->where('pagetitle', 'LIKE', $like)
                ->orWhere('alias', 'LIKE', $like);
        });

    if (!$includeUnpublished) {
        $builder->where('published', 1);
    }
    if (!$includeHidden) {
        $builder->where('searchable', 1);
    }

    $rows = $builder->orderBy('pagetitle', 'ASC')->limit($limit)->get()->toArray();

    $lowerQuery = strtolower($query);
    usort($rows, function ($a, $b) use ($lowerQuery) {
        $aTitle = strtolower((string)($a['pagetitle'] ?? ''));
        $bTitle = strtolower((string)($b['pagetitle'] ?? ''));
        $aStarts = strpos($aTitle, $lowerQuery) === 0 ? 0 : 1;
        $bStarts = strpos($bTitle, $lowerQuery) === 0 ? 0 : 1;
        if ($aStarts !== $bStarts) {
            return $aStarts - $bStarts;
        }
        return strcmp($aTitle, $bTitle);
    });

    $iconHtml = 'link';

    $output = [];
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $pagetitle = (string)($row['pagetitle'] ?? '');
        $alias = (string)($row['alias'] ?? '');

        $uri = '';
        $url = '';
        if (method_exists($evo, 'makeUrl')) {
            $uri = (string)$evo->makeUrl($id);
            $url = (string)$evo->makeUrl($id, '', '', 'full');
        }

        if ($uri !== '' && strpos($uri, '://') !== false) {
            $parsed = parse_url($uri);
            if ($parsed && isset($parsed['path'])) {
                $uri = $parsed['path'];
            }
        }
        $uri = ltrim($uri, '/');

        if ($url === '' && $uri !== '') {
            $siteUrl = defined('MODX_SITE_URL') ? MODX_SITE_URL : '/';
            $siteUrl = rtrim($siteUrl, '/');
            $url = $siteUrl . '/' . $uri;
        }

        $output[] = [
            'id' => $id,
            'pagetitle' => $pagetitle,
            'title' => $pagetitle,
            'alias' => $alias,
            'uri' => $uri,
            'url' => $url,
            'icon' => $iconHtml,
        ];
    }

    return response()->json($output);
});
