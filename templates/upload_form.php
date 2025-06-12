<?php
$existing_folders = get_existing_upload_folders();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo_uploads'])) {
    $files = $_FILES['photo_uploads'];
    $folder = sanitize_text_field($_POST['target_folder']);
    $target_path = WP_CONTENT_DIR . '/' . $folder;

    if (!file_exists($target_path)) {
        mkdir($target_path, 0755, true);
    }

    echo "<h3>✅ 上傳結果：</h3>";

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

        // 圖片壓縮成 JPG
        $src = ($ext === 'png') ? imagecreatefrompng($tmp) : imagecreatefromjpeg($tmp);
        imagejpeg($src, $save_path, 75);
        imagedestroy($src);

        $url_path = $folder . '/' . $safe_name;
        $img_url = content_url($url_path);

        echo "<p><strong>$safe_name</strong><br>";
        echo "<img src='$img_url' style='max-width:300px;border-radius:8px;'><br>";
        echo "<code>$url_path</code><br>";
        echo "📌 Shortcode: <code>[img file=\"$url_path\"]</code></p>";
    }
}
?>

<form method="post" enctype="multipart/form-data" style="margin-top:20px;">
    <label><strong>選擇圖片（可多選）：</strong></label><br>
    <input type="file" name="photo_uploads[]" accept="image/*" multiple required><br><br>

    <label><strong>儲存至資料夾：</strong>（相對於 wp-content）</label><br>
    <input type="text" name="target_folder" list="folder_suggestions" value="custom-photos/project-01" style="width:300px;" required>
    <datalist id="folder_suggestions">
        <?php foreach ($existing_folders as $folder): ?>
            <option value="<?php echo esc_attr($folder); ?>"></option>
        <?php endforeach; ?>
    </datalist><br><br>

    <button type="submit" class="button button-primary">上傳並壓縮</button>
</form>
