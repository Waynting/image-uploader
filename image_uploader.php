<?php
/*
Plugin Name: Image Uploader
Description: 從 WordPress 後台上傳圖片，自動壓縮並儲存至自訂資料夾，並產生可插入的 Shortcode，支援多檔上傳、壓縮比例調整與資料夾自動補完。
Version: 1.3
Author: Wayn Liu
*/

// 1. 加入後台選單
add_action('admin_menu', function() {
    add_menu_page('Image Uploader', '圖片上傳工具', 'manage_options', 'image-uploader', 'image_uploader_admin_page');
});

// 2. 插入樣式（可選）
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('image-uploader-style', plugin_dir_url(__FILE__) . 'assets/style.css');
});

// 3. 取得現有資料夾清單
function get_existing_upload_folders($base_folder = 'Photos') {
    $target_base = WP_CONTENT_DIR . '/' . $base_folder;
    $folders = [];

    if (is_dir($target_base)) {
        $items = scandir($target_base);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($target_base . '/' . $item)) {
                $folders[] = $base_folder . '/' . $item;
            }
        }
    }
    return $folders;
}

// 4. 後台頁面邏輯
function image_uploader_admin_page() {
    echo '<div class="wrap"><h1>Image Uploader</h1>';

    $overwrite = isset($_POST['overwrite_existing']) ? true : false;
    $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 75;

    echo '<form method="post" enctype="multipart/form-data" style="margin-bottom:20px;">';
    echo '<label><strong>選擇圖片（可多選）：</strong></label><br>';
    echo '<input type="file" name="photo_uploads[]" accept="image/*" multiple required><br><br>';
    echo '<label><strong>儲存至資料夾：</strong>（相對於 wp-content）</label><br>';
    echo '<input type="text" name="target_folder" list="folder_suggestions" value="Photos" style="width:300px;" required><br><br>';
    echo '<label><strong>JPEG 壓縮比例（10～100）：</strong></label><br>';
    echo '<input type="number" name="quality" min="10" max="100" value="' . esc_attr($quality) . '" style="width:100px;" required><br><br>';
    echo '<label><input type="checkbox" name="overwrite_existing"> 若已存在則覆蓋</label><br><br>';
    echo '<button type="submit" class="button button-primary">上傳並壓縮</button>';
    echo '</form>';

    echo '<datalist id="folder_suggestions">';
    foreach (get_existing_upload_folders() as $folder) {
        echo '<option value="' . esc_attr($folder) . '"></option>';
    }
    echo '</datalist>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo_uploads'])) {
        $files = $_FILES['photo_uploads'];
        $folder = sanitize_text_field($_POST['target_folder']);
        $target_path = WP_CONTENT_DIR . '/' . $folder;

        if (!file_exists($target_path)) {
            mkdir($target_path, 0755, true);
        }

        echo '<h3>✅ 上傳結果：</h3>';

        foreach ($files['name'] as $index => $name) {
            if ($files['error'][$index] !== UPLOAD_ERR_OK) continue;

            $tmp = $files['tmp_name'][$index];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowed)) {
                echo "<p style='color:red;'>⚠️ 檔案 $name 格式不支援</p>";
                continue;
            }

            $safe_name = basename($name);
            $save_path = $target_path . '/' . $safe_name;

            if (file_exists($save_path) && !$overwrite) {
                echo "<p style='color:orange;'>⚠️ 檔案 <strong>$safe_name</strong> 已存在，已跳過。</p>";
                continue;
            }

            $src = ($ext === 'png') ? imagecreatefrompng($tmp) : imagecreatefromjpeg($tmp);
            imagejpeg($src, $save_path, $quality);
            imagedestroy($src);

            $url_path = $folder . '/' . $safe_name;
            $img_url = content_url($url_path);

            echo "<p><strong>$safe_name</strong><br>";
            echo "<img src='$img_url' style='max-width:300px;border-radius:8px;'><br>";
            echo "<code>$url_path</code><br>";
            echo "📌 Shortcode: <code>[img file=\"$url_path\"]</code></p>";
        }
    }
    echo '</div>';
}

// 5. Shortcode: 插入圖片
add_shortcode('img', function($atts) {
    $atts = shortcode_atts(['file' => ''], $atts);
    $url = content_url($atts['file']);
    return "<img src='$url' loading='lazy' style='max-width:100%;height:auto;border-radius:8px;'>";
});
