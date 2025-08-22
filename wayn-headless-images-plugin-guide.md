# Wayn Headless Images — Plugin Scaffold & Guide
_By Wayn Liu & co‑pilot_

A complete, copy‑pasteable **WordPress plugin scaffold** to power a **headless photo workflow**:
- Store photos anywhere (NAS mount, another host, or CDN origin).
- Configure a **Base Directory** (server path) and a **Public Base URL** (how browsers fetch the same files).
- Expose **REST APIs** to list folders and images with metadata + `srcset`.
- Generate **thumbnails & WebP** to `/_thumbs/{width}/filename.ext`.
- Optional **shortcode** for classic WP pages.
- CORS for your Next.js/React frontend.
- Production notes (CDN, caching, security, tests).

---

## 0) Quick Start (TL;DR)

1. **Create plugin folder** `wp-content/plugins/wayn-headless-images/` and paste the files from this doc.
2. In WP Admin → **Plugins**, activate **Wayn Headless Images**.
3. Go to **Settings → Wayn Images** and set:
   - **Base Directory**: e.g. `/mnt/nas/photos`
   - **Public Base URL**: e.g. `https://cdn.waynspace.com/photos`
   - **Sizes**: `480,960,1440`
   - **JPEG Quality**: `80`
   - **CORS Origins**: `https://waynspace.com, https://app.waynspace.com`
4. (Optional) Run **Tools → Regenerate Thumbnails** for existing folders.
5. From your headless frontend, call:  
   `GET https://YOUR-WP/wp-json/wayn-img/v1/images?dir=FOLDER`
6. Replace Modula galleries with headless pages or the shortcode:  
   `[wayn_gallery dir="street/taipei" cols="4"]`

---

## 1) Environment Variables

If you keep operational links in a `.env`, add (examples):
```env
# WordPress JSON root
NEXT_PUBLIC_WP_API=https://waynspace.com/wp-json

# Doc link for teammates
PLUGIN_DOC_URL=https://cdn.waynspace.com/docs/wayn-headless-images-plugin-guide.md
```

---

## 2) Plugin File Layout

```
wayn-headless-images/
├─ wayn-headless-images.php        # Bootstrap
├─ inc/
│  ├─ Settings.php                 # Admin settings (paths, sizes, CORS)
│  ├─ Rest.php                     # REST routes + CORS headers
│  ├─ Files.php                    # Options, path safety, listing, srcset
│  ├─ Thumbs.php                   # Thumbnail/WebP generation
│  └─ Shortcode.php                # [wayn_gallery]
└─ tools/
   └─ Regenerate.php               # Simple admin tool to regenerate thumbs
```

Copy the following files verbatim.

---

## 3) `wayn-headless-images.php` (Bootstrap)

```php
<?php
/*
Plugin Name: Wayn Headless Images
Description: Headless-friendly image delivery from a custom directory (NAS/host) with REST endpoints, thumbnails, WebP, and optional shortcode.
Version: 1.0.0
Author: Wayn Liu
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('WHI_PATH', plugin_dir_path(__FILE__));
define('WHI_URL',  plugin_dir_url(__FILE__));

require_once WHI_PATH . 'inc/Settings.php';
require_once WHI_PATH . 'inc/Files.php';
require_once WHI_PATH . 'inc/Thumbs.php';
require_once WHI_PATH . 'inc/Rest.php';
require_once WHI_PATH . 'inc/Shortcode.php';

// Optional simple admin page for regenerating thumbs
if ( is_admin() ) {
    require_once WHI_PATH . 'tools/Regenerate.php';
}

// Activation sanity
register_activation_hook(__FILE__, function () {
    $defaults = [
        'base_dir'      => '',
        'base_url'      => '',
        'sizes'         => [480,960,1440],
        'jpeg_q'        => 80,
        'cors_origins'  => [],
    ];
    if ( ! get_option('wayn_images_options') ) {
        add_option('wayn_images_options', $defaults, '', false);
    }
});
```

---

## 4) `inc/Settings.php`

```php
<?php
if ( ! defined('ABSPATH') ) exit;

add_action('admin_menu', function () {
    add_options_page(
        'Wayn Images',
        'Wayn Images',
        'manage_options',
        'wayn-images',
        'whi_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('wayn_images', 'wayn_images_options', [
        'type' => 'array',
        'sanitize_callback' => function ($opts) {
            $out = [];
            $out['base_dir'] = isset($opts['base_dir']) ? rtrim(sanitize_text_field($opts['base_dir']), '/\\') : '';
            $out['base_url'] = isset($opts['base_url']) ? rtrim(esc_url_raw($opts['base_url']), '/') : '';
            if ( isset($opts['sizes']) ) {
                if ( is_array($opts['sizes']) ) $sizes = $opts['sizes'];
                else $sizes = array_map('trim', explode(',', $opts['sizes']));
                $out['sizes'] = array_values(array_unique(array_map('intval', array_filter($sizes))));
            } else {
                $out['sizes'] = [480,960,1440];
            }
            $out['jpeg_q'] = isset($opts['jpeg_q']) ? max(10, min(95, intval($opts['jpeg_q']))) : 80;
            if ( isset($opts['cors_origins']) ) {
                if ( is_array($opts['cors_origins']) ) $cors = $opts['cors_origins'];
                else $cors = array_map('trim', explode(',', $opts['cors_origins']));
                $out['cors_origins'] = array_values(array_filter($cors));
            } else {
                $out['cors_origins'] = [];
            }
            return $out;
        }
    ]);
});

function whi_render_settings_page() {
    if ( ! current_user_can('manage_options') ) return;
    $opts = get_option('wayn_images_options', []);
    $o = wp_parse_args($opts, [
        'base_dir' => '',
        'base_url' => '',
        'sizes'    => [480,960,1440],
        'jpeg_q'   => 80,
        'cors_origins' => []
    ]);
    ?>
    <div class="wrap">
      <h1>Wayn Images (Headless)</h1>
      <form method="post" action="options.php">
        <?php settings_fields('wayn_images'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label>Base Directory (server path)</label></th>
            <td><input type="text" name="wayn_images_options[base_dir]" value="<?php echo esc_attr($o['base_dir']); ?>" class="regular-text" placeholder="/mnt/nas/photos"/></td>
          </tr>
          <tr>
            <th scope="row"><label>Public Base URL</label></th>
            <td><input type="url" name="wayn_images_options[base_url]" value="<?php echo esc_attr($o['base_url']); ?>" class="regular-text" placeholder="https://cdn.waynspace.com/photos"/></td>
          </tr>
          <tr>
            <th scope="row"><label>Thumbnail Sizes (px)</label></th>
            <td><input type="text" name="wayn_images_options[sizes]" value="<?php echo esc_attr(is_array($o['sizes']) ? implode(',', $o['sizes']) : $o['sizes']); ?>" class="regular-text" placeholder="480,960,1440"/></td>
          </tr>
          <tr>
            <th scope="row"><label>JPEG Quality</label></th>
            <td><input type="number" min="10" max="95" name="wayn_images_options[jpeg_q]" value="<?php echo esc_attr($o['jpeg_q']); ?>"/></td>
          </tr>
          <tr>
            <th scope="row"><label>CORS Allowed Origins (comma)</label></th>
            <td><input type="text" name="wayn_images_options[cors_origins]" value="<?php echo esc_attr(is_array($o['cors_origins']) ? implode(',', $o['cors_origins']) : $o['cors_origins']); ?>" class="regular-text" placeholder="https://waynspace.com, https://app.waynspace.com"/></td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <p><em>Tip:</em> Ensure your PHP user can read the Base Directory; put the Public Base URL behind a CDN.</p>
    </div>
    <?php
}
```

---

## 5) `inc/Files.php`

```php
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
```

---

## 6) `inc/Thumbs.php`

```php
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
```

---

## 7) `inc/Rest.php`

```php
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
```

---

## 8) `inc/Shortcode.php` (Optional)

```php
<?php
if ( ! defined('ABSPATH') ) exit;

add_shortcode('wayn_gallery', function ($atts) {
    $a = shortcode_atts(['dir'=>'', 'cols'=> '4'], $atts);
    $req = new WP_REST_Request('GET', '/wayn-img/v1/images');
    $req->set_param('dir', $a['dir']);
    $req->set_param('per_page', 999);
    $res = rest_do_request($req);
    if (is_wp_error($res) || $res->is_error()) return '<p>Gallery not found.</p>';
    $data = $res->get_data();
    $items = $data['items'] ?? [];
    $cols  = max(1, min(8, intval($a['cols'])));

    ob_start(); ?>
    <div class="whi-grid whi-cols-<?php echo esc_attr($cols); ?>">
      <?php foreach ($items as $img): ?>
        <figure>
          <img loading="lazy"
               src="<?php echo esc_url($img['url']); ?>"
               srcset="<?php echo esc_attr($img['srcset']); ?>"
               sizes="(max-width: 768px) 100vw, 50vw"
               alt="<?php echo esc_attr($img['name']); ?>"/>
        </figure>
      <?php endforeach; ?>
    </div>
    <style>
      .whi-grid { display:grid; gap:8px; }
      .whi-grid.whi-cols-4 { grid-template-columns: repeat(4, 1fr); }
      .whi-grid.whi-cols-3 { grid-template-columns: repeat(3, 1fr); }
      .whi-grid.whi-cols-2 { grid-template-columns: repeat(2, 1fr); }
      @media (max-width: 900px){
        .whi-grid.whi-cols-4, .whi-grid.whi-cols-3 { grid-template-columns: repeat(2, 1fr); }
      }
      @media (max-width: 600px){
        .whi-grid { grid-template-columns: 1fr; }
      }
    </style>
    <?php
    return ob_get_clean();
});
```

---

## 9) `tools/Regenerate.php` (Simple Admin Tool)

```php
<?php
if ( ! defined('ABSPATH') ) exit;

add_action('admin_menu', function () {
    add_management_page(
        'Regenerate Wayn Thumbnails',
        'Regenerate Thumbnails',
        'manage_options',
        'whi-regenerate',
        'whi_render_regen_page'
    );
});

function whi_render_regen_page() {
    if ( ! current_user_can('manage_options') ) return;
    $msg = '';
    if ( ! empty($_POST['whi_regen_dir']) && check_admin_referer('whi_regen_action', 'whi_regen_nonce') ) {
        $dirRel = sanitize_text_field($_POST['whi_regen_dir']);
        list($dirAbs, $dirRelSafe) = whi_safe_path($dirRel);
        if ($dirAbs && is_dir($dirAbs)) {
            $opts = whi_opts();
            $sizes = is_array($opts['sizes']) ? $opts['sizes'] : [480,960,1440];
            $count = 0;
            $it = new DirectoryIterator($dirAbs);
            foreach ($it as $f) {
                if ($f->isDot() || !$f->isFile()) continue;
                $ext = strtolower($f->getExtension());
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                $rel = ($dirRelSafe ? $dirRelSafe.'/' : '') . $f->getFilename();
                whi_generate_thumbs($f->getPathname(), $rel, $sizes, intval($opts['jpeg_q']));
                $count++;
            }
            $msg = sprintf('Regenerated thumbs for %d files in %s', $count, esc_html($dirRelSafe));
        } else {
            $msg = 'Invalid directory.';
        }
    }
    ?>
    <div class="wrap">
      <h1>Regenerate Thumbnails (Wayn)</h1>
      <?php if ($msg): ?><div class="updated notice"><p><?php echo esc_html($msg); ?></p></div><?php endif; ?>
      <form method="post">
        <?php wp_nonce_field('whi_regen_action', 'whi_regen_nonce'); ?>
        <p><label>Relative Folder (under Base Directory):
          <input type="text" name="whi_regen_dir" class="regular-text" placeholder="street/taipei" required/>
        </label></p>
        <p><button class="button button-primary">Regenerate</button></p>
      </form>
    </div>
    <?php
}
```

---

## 10) Next.js Example

```tsx
// app/gallery/page.tsx
async function fetchImages(dir = '') {
  const base = process.env.NEXT_PUBLIC_WP_API!;
  const res = await fetch(`${base}/wayn-img/v1/images?dir=${encodeURIComponent(dir)}&per_page=100`, {
    next: { revalidate: 60 } // ISR cache
  });
  if (!res.ok) throw new Error('Failed to load images');
  return res.json();
}

export default async function GalleryPage() {
  const data = await fetchImages('street'); // e.g. folder under Base Directory
  return (
    <main className="p-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      {data.items.map((it: any) => (
        <figure key={it.rel} className="overflow-hidden rounded-2xl">
          <img
            src={it.url}
            srcSet={it.srcset}
            sizes="(max-width: 768px) 100vw, 25vw"
            loading="lazy"
            alt={it.name}
            style={{ display: 'block', width: '100%', height: 'auto' }}
          />
        </figure>
      ))}
    </main>
  );
}
```

**`next.config.js`**
```js
module.exports = {
  images: {
    remotePatterns: [{ protocol: 'https', hostname: 'cdn.waynspace.com' }],
  },
};
```

---

## 11) Nginx Alias Example (Serving from NAS)

```nginx
server {
  server_name cdn.waynspace.com;

  location /photos/ {
    alias /mnt/nas/photos/;
    autoindex off;
    add_header Cache-Control "public, max-age=31536000, immutable";
  }
}
```

If you can’t edit Nginx, host photos on a subdomain or connect a CDN (Cloudflare/CloudFront) to your origin path.

---

## 12) Performance Recipe (Photography)

- **WebP + srcset**: 30–70% smaller vs. raw JPEG/PNG.
- **Lazy**: `<img loading="lazy">` everywhere.
- **CDN**: HTTP/3, Brotli, long cache (immutable).
- **Display size**: publish 2560–3200 px width; keep RAW elsewhere.
- **ISR/SSG**: cache gallery pages 60–300s.
- **Minimal JS**: only add lightbox if needed.

---

## 13) Security & Robustness

- **Path traversal guard**: `whi_safe_path()` confines access to Base Directory.
- **CORS whitelist**: exact domains only.
- **Read‑only REST**: listing is anonymous, uploads stay admin-only.
- **MIME/extension checks**: allow `jpg/jpeg/png/webp`.
- **Errors**: REST returns structured errors for invalid folders.

---

## 14) Testing Checklist

- Settings saved correctly (paths, URL, sizes, quality, CORS).
- `/wayn-img/v1/folders` lists expected folders.
- `/wayn-img/v1/images?dir=...` returns width/height/srcset.
- `_thumbs/{w}/filename.jpg|webp` exist after regeneration.
- CDN returns `Cache-Control: immutable` and fast hits.
- Frontend renders grid; Lighthouse/PageSpeed improves vs. Modula.

---

## 15) Roadmap (Optional)

- **WPGraphQL** resolvers mirroring the REST payloads.
- **EXIF → API** (camera, lens, date, GPS).
- **Smart albums** by date/location.
- **Cloud backends**: S3/Cloudinary driver.
- **Admin UI**: captions, ordering, album metadata.
- **Image proxy**: on‑the‑fly transforms with disk cache.

---

© 2025 Wayn Liu. GPLv2+ for WordPress distribution.
