<?php
if ( ! defined('ABSPATH') ) exit;

function whi_generate_thumbs($absFile, $relFile, $sizes, $jpegQ = 80) {
    $info = pathinfo($absFile);
    $ext  = strtolower($info['extension'] ?? '');
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) return;

    if ($ext === 'jpg' || $ext === 'jpeg') $src = @imagecreatefromjpeg($absFile);
    elseif ($ext === 'png') $src = @imagecreatefrompng($absFile);
    else $src = @imagecreatefromwebp($absFile);
    if (!$src) return;

    $w0 = imagesx($src); $h0 = imagesy($src);

    foreach ($sizes as $w) {
        $ratio = $w / max(1,$w0);
        $h = max(1, (int)round($h0 * $ratio));
        $dst = imagecreatetruecolor($w,$h);
        imagecopyresampled($dst, $src, 0,0,0,0, $w,$h, $w0,$h0);

        $opts = whi_opts();
        $base = rtrim($opts['base_dir'], '/');
        $relDir = trim(pathinfo($relFile, PATHINFO_DIRNAME), '/');
        $file   = pathinfo($relFile, PATHINFO_BASENAME);

        $thumbRelDir = ($relDir ? $relDir.'/' : '') . '_thumbs/'.$w;
        $thumbAbsDir = $base . '/' . $thumbRelDir;
        if (!is_dir($thumbAbsDir)) wp_mkdir_p($thumbAbsDir);

        // Save JPEG
        @imagejpeg($dst, $thumbAbsDir . '/' . $file, $jpegQ);
        // Save WebP pair
        $webpName = preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $file);
        @imagewebp($dst, $thumbAbsDir . '/' . $webpName, 80);

        imagedestroy($dst);
    }
    imagedestroy($src);
}