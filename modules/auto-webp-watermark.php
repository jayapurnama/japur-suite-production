<?php

if (!defined('ABSPATH')) {
    exit;
}

class JapurSuite_AutoWebPWatermarkPro {

    /**
     * Maximum WebP file size: 50 KB
     */
    private $max_size = 51200;

    /**
     * Maximum image width: 1200px
     */
    private $max_width = 1200;

    /**
     * Get WebP/Watermark settings. Defaults intentionally match the tested
     * behavior of the original module.
     */
    private function settings() {
        $defaults = [
            'max_size_kb'            => 50,
            'max_width'              => 1200,
            'convert_webp'           => 1,
            'watermark'              => 1,
            'watermark_text'         => '',
            'watermark_position'     => 'bottom_right',
            'watermark_transparency' => 8,
            'filename_title'         => 1,
            'alt_title'              => 1,
            'attachment_title'       => 1,
            'caption'                => 1,
            'description'            => 1,
        ];
        $saved = get_option('japur_webp_settings', []);
        $settings = wp_parse_args(is_array($saved) ? $saved : [], $defaults);
        $settings['max_size_kb'] = max(1, (int) $settings['max_size_kb']);
        $settings['max_width'] = max(100, (int) $settings['max_width']);
        $settings['watermark_transparency'] = max(0, min(100, (int) $settings['watermark_transparency']));
        return $settings;
    }

    private function setting_enabled($key) {
        $settings = $this->settings();
        return !empty($settings[$key]);
    }

    public function __construct() {
        if (!JapurSuite_Core::module_enabled('webp')) return;

        // Capture the current editor/post context before the upload is processed.
        add_filter(
            'wp_handle_upload_prefilter',
            [$this, 'capture_post_context']
        );

        // Rename uploaded file
        add_filter(
            'wp_handle_upload_prefilter',
            [$this, 'rename_file']
        );

        // Process uploaded image
        add_filter(
            'wp_handle_upload',
            [$this, 'process_image']
        );

        // Automatically set attachment metadata after WordPress creates it.
        add_action(
            'add_attachment',
            [$this, 'set_attachment_metadata'],
            20
        );

        // Run once more after attachment creation to override WordPress defaults.
        add_action(
            'add_attachment',
            [$this, 'finalize_attachment_metadata'],
            99
        );
    }

    /**
     * Post ID detected during the upload request.
     */
    private $upload_post_id = 0;

    /**
     * Capture the article ID while it is still available in the upload request.
     */
    public function capture_post_context($file) {

        $this->upload_post_id = $this->detect_post_id_from_request();

        return $file;
    }

    /**
     * Detect the article/post ID from common WordPress upload contexts.
     */
    private function detect_post_id_from_request() {

        foreach (
            ['post_id', 'post', 'post_ID', 'postid', 'parent_post_id']
            as $key
        ) {

            if (empty($_REQUEST[$key])) {
                continue;
            }

            $candidate = absint($_REQUEST[$key]);

            if ($candidate && get_post($candidate)) {
                return $candidate;
            }
        }

        return 0;
    }

    /**
     * Rename uploaded file based on post title
     */
    public function rename_file($file) {

        if (!$this->setting_enabled('filename_title')) {
            return $file;
        }

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $name = '';

        $post_id = $this->upload_post_id;

        if (!$post_id) {
            $post_id = $this->detect_post_id_from_request();
        }

        if ($post_id) {

            $post = get_post($post_id);

            if ($post && !empty($post->post_title)) {
                $name = sanitize_title($post->post_title);
            }
        }

        // Fallback to website name
        if (empty($name)) {
            $name = sanitize_title(
                get_bloginfo('name')
            );
        }

        // Make filename unique
        $name .= '-' . time() . '-' . wp_rand(100, 999);

        $file['name'] = $name . '.' . $extension;

        return $file;
    }

    /**
     * Automatically set:
     *
     * 1. Attachment Title
     * 2. Alt Text
     * 3. Caption
     * 4. Description from WordPress Excerpt
     */
    public function set_attachment_metadata($attachment_id) {

        $post_id = $this->upload_post_id;

        if (!$post_id) {
            $post_id = $this->detect_parent_post_id($attachment_id);
        }

        $post_title = '';
        $post_excerpt = '';

        /**
         * Get data from related post
         */
        if ($post_id) {

            $post = get_post($post_id);

            if ($post) {

                if (!empty($post->post_title)) {

                    $post_title = wp_strip_all_tags(
                        $post->post_title
                    );
                }

                /**
                 * Get WordPress Excerpt
                 */
                if (!empty($post->post_excerpt)) {

                    $post_excerpt = wp_strip_all_tags(
                        $post->post_excerpt
                    );

                    $post_excerpt = trim(
                        $post_excerpt
                    );
                }
            }
        }

        /**
         * Fallback title from filename
         */
        if (empty($post_title)) {

            $file = get_attached_file(
                $attachment_id
            );

            if ($file) {

                $filename = pathinfo(
                    $file,
                    PATHINFO_FILENAME
                );

                $filename = str_replace(
                    ['-', '_'],
                    ' ',
                    $filename
                );

                $post_title = ucwords(
                    $filename
                );
            }
        }

        /**
         * Stop if no title/data available
         */
        if (empty($post_title)) {
            return;
        }

        /**
         * If WordPress Excerpt is empty,
         * use post title as Description fallback.
         */
        if (empty($post_excerpt)) {
            $post_excerpt = $post_title;
        }

        $attachment = get_post(
            $attachment_id
        );

        if (!$attachment) {
            return;
        }

        /**
         * ==========================================
         * 1. ATTACHMENT TITLE
         * ==========================================
         *
         * Only update if empty.
         */
        /**
         * Always use the related article title.
         * This prevents WordPress automatic titles such as
         * "Konsep otomatis" from remaining in the attachment title.
         */
        if ($this->setting_enabled('attachment_title')) {
            wp_update_post([
                'ID'         => $attachment_id,
                'post_title' => $post_title,
            ]);
        }

        /**
         * ==========================================
         * 2. CAPTION / KETERANGAN
         * ==========================================
         */
        if ($this->setting_enabled('caption')) {
            $focus_keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $focus_keyphrase = trim(wp_strip_all_tags((string) $focus_keyphrase));
            $caption = 'Ilustrasi: ' . ($focus_keyphrase !== '' ? $focus_keyphrase : $post_title);
            wp_update_post([
                'ID'          => $attachment_id,
                'post_excerpt' => $caption,
            ]);
        }

        /**
         * ==========================================
         * 3. DESCRIPTION / DESKRIPSI
         * ==========================================
         */
        if ($this->setting_enabled('description')) {
            wp_update_post([
                'ID'           => $attachment_id,
                'post_content' => $post_excerpt,
            ]);
        }

        /**
         * ==========================================
         * 4. ALT TEXT
         * ==========================================
         */
        if ($this->setting_enabled('alt_title')) {
            update_post_meta(
                $attachment_id,
                '_wp_attachment_image_alt',
                $post_title
            );
        }
    }

    /**
     * Final metadata pass after WordPress has completed attachment creation.
     * This makes the article title authoritative for the attachment title/caption.
     */
    public function finalize_attachment_metadata($attachment_id) {

        $post_id = $this->upload_post_id;

        if (!$post_id) {
            $post_id = $this->detect_parent_post_id($attachment_id);
        }

        if (!$post_id) {
            return;
        }

        $post = get_post($post_id);

        if (!$post || empty($post->post_title)) {
            return;
        }

        $title = wp_strip_all_tags($post->post_title);
        $excerpt = !empty($post->post_excerpt)
            ? trim(wp_strip_all_tags($post->post_excerpt))
            : $title;

        $update = ['ID' => $attachment_id];
        if ($this->setting_enabled('attachment_title')) {
            $update['post_title'] = $title;
        }
        if ($this->setting_enabled('caption')) {
            $focus_keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $focus_keyphrase = trim(wp_strip_all_tags((string) $focus_keyphrase));
            $update['post_excerpt'] = 'Ilustrasi: ' . ($focus_keyphrase !== '' ? $focus_keyphrase : $title);
        }
        if ($this->setting_enabled('description')) {
            $update['post_content'] = $excerpt;
        }
        if (count($update) > 1) {
            wp_update_post($update);
        }
        if ($this->setting_enabled('alt_title')) {
            update_post_meta(
                $attachment_id,
                '_wp_attachment_image_alt',
                $title
            );
        }
    }

    /**
     * Detect the article/post associated with the uploaded image.
     *
     * Priority:
     * 1. Explicit post_id from the uploader.
     * 2. post_parent of the attachment.
     * 3. post ID supplied by common editor/uploader requests.
     */
    private function detect_parent_post_id($attachment_id = 0) {

        $post_id = 0;

        if (!empty($_REQUEST['post_id'])) {
            $candidate = absint($_REQUEST['post_id']);

            if ($candidate && get_post($candidate)) {
                $post_id = $candidate;
            }
        }

        if (!$post_id && $attachment_id) {
            $attachment = get_post($attachment_id);

            if ($attachment && !empty($attachment->post_parent)) {
                $parent = get_post($attachment->post_parent);

                if ($parent && $parent->post_type !== 'attachment') {
                    $post_id = absint($attachment->post_parent);
                }
            }
        }

        if (!$post_id) {
            foreach (['post', 'post_ID', 'postid', 'parent_post_id'] as $key) {
                if (!empty($_REQUEST[$key])) {
                    $candidate = absint($_REQUEST[$key]);

                    if ($candidate && get_post($candidate)) {
                        $post_id = $candidate;
                        break;
                    }
                }
            }
        }

        return $post_id;
    }

    /**
     * Process uploaded image
     */
    public function process_image($upload) {

        $file = $upload['file'];
        $settings = $this->settings();

        // When WebP conversion is disabled, leave the uploaded image untouched.
        if (empty($settings['convert_webp'])) {
            return $upload;
        }

        if (!file_exists($file)) {
            return $upload;
        }

        /**
         * Get image information
         */
        $info = @getimagesize($file);

        if (!$info) {
            return $upload;
        }

        $mime = $info['mime'];

        /**
         * Create image resource
         */
        switch ($mime) {

            case 'image/jpeg':

                $image = @imagecreatefromjpeg(
                    $file
                );

                break;

            case 'image/png':

                $image = @imagecreatefrompng(
                    $file
                );

                if ($image) {

                    imagealphablending(
                        $image,
                        true
                    );

                    imagesavealpha(
                        $image,
                        true
                    );
                }

                break;

            default:

                return $upload;
        }

        if (!$image) {
            return $upload;
        }

        /**
         * Resize
         */
        $image = $this->resize_image(
            $image
        );

        /**
         * Watermark
         */
        $image = $this->apply_watermark(
            $image
        );

        /**
         * Check WebP support
         */
        if (!function_exists('imagewebp')) {

            imagedestroy($image);

            return $upload;
        }

        /**
         * WebP filename
         */
        $webp_file = preg_replace(
            '/\.(jpg|jpeg|png)$/i',
            '.webp',
            $file
        );

        if (empty($webp_file)) {

            imagedestroy($image);

            return $upload;
        }

        /**
         * Try quality from 80 down to 10.
         */
        $quality = 80;
        $data = '';

        while ($quality >= 10) {

            ob_start();

            imagewebp(
                $image,
                null,
                $quality
            );

            $current_data = ob_get_clean();

            if (!empty($current_data)) {

                $data = $current_data;

                if (
                    strlen($data) <=
                    ((int) $settings['max_size_kb'] * 1024)
                ) {
                    break;
                }
            }

            $quality -= 5;
        }

        /**
         * WebP generation failed
         */
        if (empty($data)) {

            imagedestroy($image);

            return $upload;
        }

        /**
         * Save WebP
         */
        $saved = file_put_contents(
            $webp_file,
            $data
        );

        if ($saved === false) {

            imagedestroy($image);

            return $upload;
        }

        if (!file_exists($webp_file)) {

            imagedestroy($image);

            return $upload;
        }

        /**
         * Remove original image
         */
        @unlink($file);

        /**
         * Update upload data
         */
        $upload['file'] = $webp_file;

        $upload['type'] = 'image/webp';

        $upload['url'] = str_replace(
            basename($file),
            basename($webp_file),
            $upload['url']
        );

        /**
         * Free memory
         */
        imagedestroy($image);

        return $upload;
    }

    /**
     * Resize image to maximum width.
     */
    private function resize_image($image) {

        $width = imagesx($image);
        $height = imagesy($image);

        $settings = $this->settings();
        $max_width = (int) $settings['max_width'];

        if ($width <= $max_width) {
            return $image;
        }

        $new_width = $max_width;

        $new_height = (int) floor(
            $height *
            ($new_width / $width)
        );

        $new_image = imagecreatetruecolor(
            $new_width,
            $new_height
        );

        /**
         * Preserve transparency
         */
        imagealphablending(
            $new_image,
            false
        );

        imagesavealpha(
            $new_image,
            true
        );

        $transparent = imagecolorallocatealpha(
            $new_image,
            0,
            0,
            0,
            127
        );

        imagefill(
            $new_image,
            0,
            0,
            $transparent
        );

        /**
         * Resample
         */
        imagecopyresampled(
            $new_image,
            $image,
            0,
            0,
            0,
            0,
            $new_width,
            $new_height,
            $width,
            $height
        );

        imagedestroy($image);

        return $new_image;
    }

    /**
     * Apply watermark
     */
    private function apply_watermark($image) {

        $settings = $this->settings();
        if (empty($settings['watermark'])) {
            return $image;
        }

        $text = trim($settings['watermark_text']);
        if ($text === '') {
            $text = get_bloginfo('name');
        }

        if (empty($text)) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        /**
         * White semi-transparent color
         */
        $color = imagecolorallocatealpha(
            $image,
            255,
            255,
            255,
            (int) round(((int) $settings['watermark_transparency']) * 127 / 100)
        );

        /**
         * GD built-in font
         */
        $font_size = 5;

        $text_width =
            imagefontwidth($font_size) *
            strlen($text);

        $text_height =
            imagefontheight($font_size);

        $margin = 15;
        switch ($settings['watermark_position']) {
            case 'top_left':
                $x = $margin;
                $y = $margin;
                break;
            case 'top_right':
                $x = $width - $text_width - $margin;
                $y = $margin;
                break;
            case 'bottom_left':
                $x = $margin;
                $y = $height - $text_height - $margin;
                break;
            case 'bottom_right':
            default:
                $x = $width - $text_width - $margin;
                $y = $height - $text_height - $margin;
                break;
        }

        /**
         * Prevent negative coordinates
         */
        if ($x < 5) {
            $x = 5;
        }

        if ($y < 5) {
            $y = 5;
        }

        imagestring(
            $image,
            $font_size,
            $x,
            $y,
            $text,
            $color
        );

        return $image;
    }
}

/**
 * Start plugin
 */
new JapurSuite_AutoWebPWatermarkPro();