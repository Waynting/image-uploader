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