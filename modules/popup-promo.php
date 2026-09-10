<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Popup Promo Random v4.2 integrated into Japur Suite.
 * Canonical category option: pgm_category_ids.
 */
final class JapurSuite_PopupPromo {
    public function __construct() {
        add_action('wp_footer', [$this, 'render']);
    }

    private function get_category_ids() {
        $default_category = get_category_by_slug('promo');
        $default_category_id = $default_category ? absint($default_category->term_id) : 0;
        $category_ids = get_option('pgm_category_ids', null);

        // Backward compatibility with the earlier Japur Suite category option.
        if (!is_array($category_ids)) {
            $category_ids = get_option('japur_suite_popup_categories', $default_category_id ? [$default_category_id] : []);
        }
        if (!is_array($category_ids)) {
            $category_ids = $category_ids ? [absint($category_ids)] : [];
        }
        return array_values(array_filter(array_map('absint', $category_ids)));
    }

    public function render() {
        if (!JapurSuite_Core::module_enabled('popup')) return;
        if (is_admin()) return;

        $category_ids = $this->get_category_ids();
        if (!$category_ids) return;

        // Ambil artikel random dari kategori yang dipilih.
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'category__in'   => $category_ids,
            'post_status'    => 'publish'
        ];

        $query = new WP_Query($args);
        if (!$query->have_posts()) return;

        $query->the_post();
        $popup_post_id = get_the_ID();
        $image = get_the_post_thumbnail_url($popup_post_id, 'large');
        if (!$image) {
            wp_reset_postdata();
            return;
        }
        $title = get_the_title();
        $link  = get_permalink();
        wp_reset_postdata();

        // Aturan dari plugin v4.2: jangan tampil jika artikel popup adalah artikel yang sedang dibaca.
        if (is_single() && get_queried_object_id() === $popup_post_id) return;
        ?>
        <style>
        #pgm-popup { position:fixed; bottom:20px; right:20px; width:280px; background:#fff; border-radius:14px; box-shadow:0 4px 20px rgba(0,0,0,.3); display:none; flex-direction:column; align-items:center; text-align:center; overflow:hidden; z-index:9999; opacity:0; transform:scale(.8); transition:opacity .4s ease,transform .4s ease; }
        #pgm-popup.show { display:flex; opacity:1; transform:scale(1); }
        #pgm-popup img { width:100%; height:auto; display:block; }
        #pgm-popup p { margin:8px 12px 6px; font-size:14px; color:#333; line-height:1.4em; font-weight:600; }
        #pgm-popup .pgm-btn { display:inline-block; margin-bottom:12px; padding:8px 14px; background:#0073aa; color:#fff; border-radius:6px; text-decoration:none; font-size:14px; transition:.2s; }
        #pgm-popup .pgm-btn:hover { background:#005a87; }
        #pgm-close { position:absolute; top:8px; right:8px; background:rgba(255,255,255,.9); border:none; cursor:pointer; font-size:18px; color:#333; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; padding:0; line-height:1; }
        @media(max-width:768px) { #pgm-popup { bottom:auto; right:auto; top:50%; left:50%; width:80%; max-width:320px; transform:translate(-50%,-50%) scale(.8); } #pgm-popup.show { transform:translate(-50%,-50%) scale(1); } }
        </style>
        <div id="pgm-popup">
            <button id="pgm-close" type="button">X</button>
            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>">
            <p><?php echo esc_html($title); ?></p>
            <a href="<?php echo esc_url($link); ?>" class="pgm-btn">Kunjungi Artikel</a>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const popup = document.getElementById('pgm-popup');
            const closeBtn = document.getElementById('pgm-close');
            const promoLink = document.querySelector('#pgm-popup .pgm-btn');
            if (!popup || !closeBtn || !promoLink) return;
            setTimeout(() => {
                if (!sessionStorage.getItem('pgm_closed')) {
                    popup.style.display = 'flex';
                    requestAnimationFrame(() => popup.classList.add('show'));
                }
            }, 2000);
            closeBtn.addEventListener('click', () => {
                popup.classList.remove('show');
                setTimeout(() => popup.style.display = 'none', 400);
                sessionStorage.setItem('pgm_closed', 'true');
            });
            promoLink.addEventListener('click', () => {
                sessionStorage.setItem('pgm_closed', 'true');
            });
        });
        </script>
        <?php
    }
}

new JapurSuite_PopupPromo();
