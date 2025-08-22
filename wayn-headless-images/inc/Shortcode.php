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