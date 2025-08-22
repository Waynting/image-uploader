<?php
if ( ! defined('ABSPATH') ) exit;

function whi_opts() {
    return get_option('wayn_images_options', [
        'base_dir' => '',
        'base_url' => '',
        'sizes'    => [480,960,1440],
        'jpeg_q'   => 80,
        'cors_origins' => [],
    ]);
}

function whi_public_url($relPath) {
    $opts = whi_opts();
    $baseURL = rtrim($opts['base_url'], '/');
    $relPath = ltrim($relPath, '/');
    return $baseURL . '/' . $relPath;
}

function whi_safe_path($rel) {
    $opts = whi_opts();
    $base = rtrim($opts['base_dir'], '/\\');
    $rel  = ltrim(str_replace(['..','\\'], ['','/'], (string)$rel), '/');
    $full = $base . '/' . $rel;
    $realBase = realpath($base);
    $realFull = realpath($full) ?: $full;
    if ($realBase && str_starts_with($realFull, $realBase)) {
        return [$full, $rel];
    }
    return [null, null];
}

function whi_srcset($relOriginal) {
    $opts  = whi_opts();
    $sizes = is_array($opts['sizes']) ? $opts['sizes'] : [480,960,1440];

    $parts = pathinfo($relOriginal);
    $srcs  = [];
    foreach ($sizes as $w) {
        $thumbRel = ($parts['dirname'] !== '.')
            ? $parts['dirname'].'/_thumbs/'.$w.'/'.$parts['basename']
            : '_thumbs/'.$w.'/'.$parts['basename'];
        $srcs[] = whi_public_url($thumbRel) . ' ' . $w . 'w';
    }
    return implode(', ', $srcs);
}

function whi_list_images($dirRel, $extFilter = null) {
    list($dirAbs, $dirRelSafe) = whi_safe_path($dirRel);
    if (!$dirAbs || !is_dir($dirAbs)) return new WP_Error('bad_dir', 'Invalid directory');

    $allowed = ['jpg','jpeg','png','webp'];
    if ($extFilter) {
        $filter = array_intersect($allowed, array_map('strtolower', array_map('trim', explode(',', $extFilter))));
    } else {
        $filter = $allowed;
    }

    $files = [];
    $h = opendir($dirAbs);
    if ($h) {
        while (($file = readdir($h)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $filter, true)) {
                $full = $dirAbs . '/' . $file;
                if (is_file($full)) {
                    $stat = @stat($full);
                    [$width, $height] = @getimagesize($full) ?: [null, null];
                    $rel = ($dirRelSafe ? $dirRelSafe . '/' : '') . $file;
                    $files[] = [
                        'name'   => $file,
                        'rel'    => $rel,
                        'url'    => whi_public_url($rel),
                        'size'   => $stat ? intval($stat['size']) : null,
                        'mtime'  => $stat ? intval($stat['mtime']) : null,
                        'width'  => $width,
                        'height' => $height,
                        'srcset' => whi_srcset($rel),
                    ];
                }
            }
        }
        closedir($h);
    }
    return $files;
}