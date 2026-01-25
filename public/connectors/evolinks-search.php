<?php
/**
 * EVO links search endpoint for TinyMCE 8.
 */

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 4) . '/index.php';

if (!isset($_SESSION['mgrValidated'])) {
    http_response_code(403);
    exit;
}

$modx->db->connect();
if (empty($modx->config)) {
    $modx->getSettings();
}

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($query === '') {
    echo json_encode([]);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit <= 0) {
    $limit = 10;
}
if ($limit > 50) {
    $limit = 50;
}

$includeUnpublished = isset($_GET['includeUnpublished']) && $_GET['includeUnpublished'] === '1';
$includeHidden = isset($_GET['includeHidden']) && $_GET['includeHidden'] === '1';

$escaped = $modx->db->escape($query);
$like = '%' . $escaped . '%';

$where = "deleted=0";
if (!$includeUnpublished) {
    $where .= " AND published=1";
}
if (!$includeHidden) {
    $where .= " AND searchable=1";
}
$where .= " AND (pagetitle LIKE '" . $like . "' OR alias LIKE '" . $like . "')";

$result = $modx->db->select(
    "id,pagetitle,alias",
    $modx->getFullTableName('site_content'),
    $where,
    "pagetitle ASC",
    $limit
);

$rows = [];
if ($result) {
    while ($row = $modx->db->getRow($result)) {
        $rows[] = $row;
    }
}

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

$output = [];
foreach ($rows as $row) {
    $id = (int)$row['id'];
    $pagetitle = (string)$row['pagetitle'];
    $alias = (string)$row['alias'];

    $uri = '';
    $url = '';
    if (isset($modx) && method_exists($modx, 'makeUrl')) {
        $uri = (string)$modx->makeUrl($id);
        $url = (string)$modx->makeUrl($id, '', '', 'full');
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
        'url' => $url
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output);
