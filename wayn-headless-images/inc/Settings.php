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