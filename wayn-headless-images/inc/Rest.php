<?php
if ( ! defined('ABSPATH') ) exit;

add_action('rest_api_init', function () {
    register_rest_route('wayn-img/v1', '/folders', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'whi_rest_folders',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wayn-img/v1', '/images', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'whi_rest_images',
        'permission_callback' => '__return_true',
        'args' => [
            'dir' => ['type'=>'string','required'=>false],
            'page'=> ['type'=>'integer','default'=>1],
            'per_page'=> ['type'=>'integer','default'=>50],
            'sort'=> ['type'=>'string','enum'=>['name','date','size'],'default'=>'name'],
            'order'=> ['type'=>'string','enum'=>['asc','desc'],'default'=>'asc'],
            'ext'  => ['type'=>'string','required'=>false],
        ],
    ]);
});

function whi_rest_folders(WP_REST_Request $req) {
    $opts = whi_opts();
    $base = rtrim($opts['base_dir'], '/');
    $out  = [];
    if (!is_dir($base)) return rest_ensure_response(['folders' => []]);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $path => $info) {
        if ($info->isDir()) {
            $rel = ltrim(str_replace($base, '', $path), '/\\');
            if ($rel !== '' && strpos($rel, '_thumbs') === false) {
                $out[] = str_replace('\\', '/', $rel);
            }
        }
    }
    sort($out);
    return rest_ensure_response(['folders' => $out]);
}

function whi_rest_images(WP_REST_Request $req) {
    $dirRel = (string)$req->get_param('dir');
    $page = max(1, intval($req->get_param('page')));
    $pp   = max(1, min(200, intval($req->get_param('per_page'))));
    $sort = $req->get_param('sort') ?: 'name';
    $order= strtolower($req->get_param('order') ?: 'asc');
    $extQ = $req->get_param('ext');

    $items = whi_list_images($dirRel, $extQ);
    if (is_wp_error($items)) return $items;

    usort($items, function($a,$b) use ($sort,$order){
        $va = $a[$sort] ?? $a['name'];
        $vb = $b[$sort] ?? $b['name'];
        if ($va == $vb) return 0;
        $cmp = ($va < $vb) ? -1 : 1;
        return $order === 'asc' ? $cmp : -$cmp;
    });

    $total = count($items);
    $start = ($page - 1) * $pp;
    $paged = array_slice($items, $start, $pp);

    return rest_ensure_response([
        'dir'     => $dirRel,
        'page'    => $page,
        'perPage' => $pp,
        'total'   => $total,
        'items'   => $paged
    ]);
}

// CORS: allow your frontends
add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
    $opts = whi_opts();
    $allowed = $opts['cors_origins'] ?? [];
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string)$_SERVER['HTTP_ORIGIN']) : '';
    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
    return $served;
}, 10, 4);