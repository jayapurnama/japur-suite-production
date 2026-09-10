<?php
if (!defined('ABSPATH')) {
    exit;
}

class JapurSuite_JAPI_V253 {

    const NONCE   = 'japi_v25_nonce';
    const VERSION = '2.5.3';

    public function __construct() {
        if (!JapurSuite_Core::module_enabled('japi')) return;

        add_action(
            'enqueue_block_editor_assets',
            [$this, 'assets']
        );

        add_action(
            'wp_ajax_japi_v25_import',
            [$this, 'import']
        );

        add_action(
            'init',
            [$this, 'register_yoast_meta']
        );
    }

    private function import_setting_enabled($key) {
        return (bool) get_option('japur_japi_import_' . sanitize_key($key), 1);
    }

    /**
     * Register Yoast metadata agar dapat
     * dikenali oleh editor/REST API.
     */
    public function register_yoast_meta() {

        $auth_callback = function () {
            return current_user_can('edit_posts');
        };

        register_post_meta(
            'post',
            '_yoast_wpseo_focuskw',
            [
                'type'          => 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => $auth_callback,
            ]
        );

        register_post_meta(
            'post',
            '_yoast_wpseo_metadesc',
            [
                'type'          => 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => $auth_callback,
            ]
        );
    }

    /**
     * Load JavaScript dan CSS.
     */
    public function assets() {

        $base = JAPUR_SUITE_URL . 'assets/javanese-auto-post-importer';

        wp_enqueue_script(
            'japi-v253',
            $base . '/editor.js',
            [
                'wp-blocks',
                'wp-block-editor',
                'wp-data',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-i18n'
            ],
            self::VERSION,
            true
        );

        wp_enqueue_style(
            'japi-v253',
            $base . '/editor.css',
            [],
            self::VERSION
        );

        wp_localize_script(
            'japi-v253',
            'JAPI25',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::NONCE),
            ]
        );
    }

    /**
     * Parser format artikel.
     *
     * Mendukung:
     *
     * [TITLE] Judul
     *
     * atau:
     *
     * [TITLE]
     * Judul
     *
     * CONTENT selalu dibaca sampai akhir.
     */
    private function parse($raw) {

        $raw = str_replace(
            ["\r\n", "\r"],
            "\n",
            trim($raw)
        );

        $result = [
            'TITLE'           => '',
            'DESCRIPTION'     => '',
            'CATEGORY'        => '',
            'FOCUS_KEYPHRASE' => '',
            'TAGS'            => '',
            'CONTENT'         => '',
        ];

        /*
         * Cari marker CONTENT.
         */
        if (
            preg_match(
                '/^\[CONTENT\][ \t]*(.*)$/mi',
                $raw,
                $match,
                PREG_OFFSET_CAPTURE
            )
        ) {

            $content_line =
                $match[0][0];

            $content_position =
                $match[0][1];

            $content_inline =
                isset($match[1][0])
                    ? trim($match[1][0])
                    : '';

            $content_start =
                $content_position +
                strlen($content_line);

            $content_rest =
                substr(
                    $raw,
                    $content_start
                );

            $content_rest =
                trim($content_rest);

            if (
                $content_inline !== '' &&
                $content_rest !== ''
            ) {

                $result['CONTENT'] =
                    $content_inline .
                    "\n\n" .
                    $content_rest;

            } elseif (
                $content_inline !== ''
            ) {

                $result['CONTENT'] =
                    $content_inline;

            } else {

                $result['CONTENT'] =
                    $content_rest;
            }

            $header =
                substr(
                    $raw,
                    0,
                    $content_position
                );

        } else {

            $header = $raw;
        }

        /*
         * Field header.
         */
        $pattern =
            '/^\[(TITLE|DESCRIPTION|CATEGORY|FOCUS_KEYPHRASE|TAGS)\][ \t]*(.*)$/mi';

        preg_match_all(
            $pattern,
            $header,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if (!empty($matches[0])) {

            $fields = [];

            foreach (
                $matches[1] as $i => $match
            ) {

                $key =
                    strtoupper(
                        trim($match[0])
                    );

                $full_line =
                    $matches[0][$i][0];

                $start =
                    $matches[0][$i][1];

                $inline =
                    isset($matches[2][$i][0])
                        ? trim($matches[2][$i][0])
                        : '';

                $fields[] = [
                    'key'    => $key,
                    'start'  => $start,
                    'length' => strlen($full_line),
                    'inline' => $inline,
                ];
            }

            $count =
                count($fields);

            for (
                $i = 0;
                $i < $count;
                $i++
            ) {

                $field =
                    $fields[$i];

                $key =
                    $field['key'];

                /*
                 * Format satu baris:
                 *
                 * [TITLE] Judul
                 */
                if (
                    $field['inline'] !== ''
                ) {

                    $result[$key] =
                        $field['inline'];

                    continue;
                }

                /*
                 * Format dua baris:
                 *
                 * [TITLE]
                 * Judul
                 */
                $value_start =
                    $field['start'] +
                    $field['length'];

                if (
                    $i + 1 < $count
                ) {

                    $value_end =
                        $fields[$i + 1]['start'];

                } else {

                    $value_end =
                        strlen($header);
                }

                $value =
                    substr(
                        $header,
                        $value_start,
                        $value_end -
                        $value_start
                    );

                $result[$key] =
                    trim($value);
            }
        }

        return $result;
    }

    /**
     * Cari ID kategori.
     */
    private function category_ids(
        $text,
        &$missing
    ) {

        $names =
            preg_split(
                '/[\n,]+/',
                $text
            );

        $ids = [];
        $missing = [];

        foreach ($names as $name) {

            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $term =
                get_term_by(
                    'name',
                    $name,
                    'category'
                );

            if (!$term) {

                $term =
                    get_term_by(
                        'slug',
                        sanitize_title($name),
                        'category'
                    );
            }

            if (
                $term &&
                !is_wp_error($term)
            ) {

                $ids[] =
                    (int) $term->term_id;

            } else {

                $missing[] =
                    $name;
            }
        }

        return array_values(
            array_unique($ids)
        );
    }

    /**
     * Cari ID tag.
     */
    private function tag_ids(
        $text,
        &$missing
    ) {

        $missing = [];

        if (
            trim($text) === ''
        ) {

            return [];
        }

        $names =
            preg_split(
                '/[\n,]+/',
                $text
            );

        $ids = [];

        foreach ($names as $name) {

            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $term =
                get_term_by(
                    'name',
                    $name,
                    'post_tag'
                );

            if (!$term) {

                $term =
                    get_term_by(
                        'slug',
                        sanitize_title($name),
                        'post_tag'
                    );
            }

            if (
                $term &&
                !is_wp_error($term)
            ) {

                $ids[] =
                    (int) $term->term_id;

            } else {

                $missing[] =
                    $name;
            }
        }

        return array_values(
            array_unique($ids)
        );
    }

    /**
     * Membuat Gutenberg block dari
     * HTML biasa.
     */
    private function html_to_blocks($content) {

        $content =
            trim($content);

        if (
            $content === ''
        ) {

            return '';
        }

        /*
         * Jika sudah berupa Gutenberg blocks,
         * parse dan serialize ulang.
         */
        if (
            strpos(
                $content,
                '<!-- wp:'
            ) !== false
        ) {

            $blocks =
                parse_blocks($content);

            if (
                !empty($blocks)
            ) {

                return serialize_blocks(
                    $blocks
                );
            }
        }

        /*
         * Bersihkan HTML yang berbahaya,
         * tetapi tetap mempertahankan HTML artikel.
         */
        $content =
            wp_kses_post($content);

        /*
         * Normalisasi line break.
         */
        $content =
            preg_replace(
                "/\r\n|\r/",
                "\n",
                $content
            );

        /*
         * Jika konten berupa HTML,
         * pecah elemen utama menjadi Gutenberg block.
         */
        $pattern =
            '~(<(?:p|h[1-6]|ul|ol|blockquote|pre|figure|table|hr|div)\b[^>]*>.*?</(?:p|h[1-6]|ul|ol|blockquote|pre|figure|table|div)>|<hr\b[^>]*/?>)~is';

        preg_match_all(
            $pattern,
            $content,
            $matches
        );

        $blocks = [];

        if (
            !empty($matches[1])
        ) {

            foreach (
                $matches[1] as $html
            ) {

                $html =
                    trim($html);

                if (
                    $html === ''
                ) {
                    continue;
                }

                /*
                 * Heading.
                 */
                if (
                    preg_match(
                        '/^<h([1-6])\b([^>]*)>(.*?)<\/h\1>$/is',
                        $html,
                        $m
                    )
                ) {

                    $level =
                        (int) $m[1];

                    $attrs =
                        [
                            'level' =>
                                $level,
                        ];

                    $blocks[] = [
                        'blockName' =>
                            'core/heading',

                        'attrs' =>
                            $attrs,

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * Paragraph.
                 */
                if (
                    preg_match(
                        '/^<p\b([^>]*)>(.*?)<\/p>$/is',
                        $html
                    )
                ) {

                    $blocks[] = [
                        'blockName' =>
                            'core/paragraph',

                        'attrs' =>
                            [],

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * List.
                 */
                if (
                    preg_match(
                        '/^<(ul|ol)\b/i',
                        $html,
                        $m
                    )
                ) {

                    $ordered =
                        strtolower($m[1]) === 'ol';

                    $attrs = [];

                    if ($ordered) {
                        $attrs['ordered'] = true;
                    }

                    $blocks[] = [
                        'blockName' =>
                            'core/list',

                        'attrs' =>
                            $attrs,

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * Quote.
                 */
                if (
                    preg_match(
                        '/^<blockquote\b/i',
                        $html
                    )
                ) {

                    $blocks[] = [
                        'blockName' =>
                            'core/quote',

                        'attrs' =>
                            [],

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * Pre / Code.
                 */
                if (
                    preg_match(
                        '/^<pre\b/i',
                        $html
                    )
                ) {

                    $blocks[] = [
                        'blockName' =>
                            'core/code',

                        'attrs' =>
                            [],

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * Image / Figure.
                 */
                if (
                    preg_match(
                        '/^<figure\b/i',
                        $html
                    )
                ) {

                    $blocks[] = [
                        'blockName' =>
                            'core/image',

                        'attrs' =>
                            [],

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * Table.
                 */
                if (
                    preg_match(
                        '/^<table\b/i',
                        $html
                    )
                ) {

                    $blocks[] = [
                        'blockName' =>
                            'core/table',

                        'attrs' =>
                            [],

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * HR.
                 */
                if (
                    preg_match(
                        '/^<hr\b/i',
                        $html
                    )
                ) {

                    $blocks[] = [
                        'blockName' =>
                            'core/separator',

                        'attrs' =>
                            [],

                        'innerBlocks' =>
                            [],

                        'innerHTML' =>
                            $html,

                        'innerContent' =>
                            [$html],
                    ];

                    continue;
                }

                /*
                 * Div atau HTML lain:
                 * masukkan sebagai paragraph jika
                 * masih memiliki teks.
                 */
                $text =
                    trim(
                        wp_strip_all_tags(
                            $html
                        )
                    );

                if (
                    $text !== ''
                ) {

                    $paragraph =
                        wpautop($html);

                    $paragraph_blocks =
                        parse_blocks(
                            $paragraph
                        );

                    if (
                        !empty($paragraph_blocks)
                    ) {

                        foreach (
                            $paragraph_blocks
                            as $block
                        ) {

                            if (
                                empty(
                                    $block['blockName']
                                )
                            ) {

                                $block =
                                    [
                                        'blockName' =>
                                            'core/paragraph',

                                        'attrs' =>
                                            [],

                                        'innerBlocks' =>
                                            [],

                                        'innerHTML' =>
                                            '<p>' .
                                            wp_kses_post(
                                                $text
                                            ) .
                                            '</p>',

                                        'innerContent' =>
                                            [
                                                '<p>' .
                                                wp_kses_post(
                                                    $text
                                                ) .
                                                '</p>'
                                            ],
                                    ];
                            }

                            $blocks[] =
                                $block;
                        }
                    }
                }
            }
        }

        /*
         * Jika regex tidak menemukan elemen utama,
         * perlakukan sebagai teks biasa.
         */
        if (
            empty($blocks)
        ) {

            $plain =
                trim(
                    wp_strip_all_tags(
                        $content
                    )
                );

            if (
                $plain === ''
            ) {

                return '';
            }

            $paragraphs =
                preg_split(
                    "/\n\s*\n/",
                    $plain
                );

            foreach (
                $paragraphs as $paragraph
            ) {

                $paragraph =
                    trim($paragraph);

                if (
                    $paragraph === ''
                ) {
                    continue;
                }

                $html =
                    '<p>' .
                    esc_html($paragraph) .
                    '</p>';

                $blocks[] = [
                    'blockName' =>
                        'core/paragraph',

                    'attrs' =>
                        [],

                    'innerBlocks' =>
                        [],

                    'innerHTML' =>
                        $html,

                    'innerContent' =>
                        [$html],
                ];
            }
        }

        /*
         * Serialize menjadi format Gutenberg asli.
         */
        return serialize_blocks(
            $blocks
        );
    }

    /**
     * CONTENT processor.
     */
    private function prepare_content($content) {

        return $this->html_to_blocks(
            $content
        );
    }

    /**
     * AJAX IMPORT.
     */
    public function import() {

        if (
            !current_user_can('edit_posts')
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'Tidak memiliki izin.'
                ],
                403
            );
        }

        if (
            !check_ajax_referer(
                self::NONCE,
                'nonce',
                false
            )
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'Sesi keamanan tidak valid. Silakan muat ulang editor.'
                ],
                403
            );
        }

        $post_id =
            absint(
                $_POST['postId'] ?? 0
            );

        $raw =
            wp_unslash(
                $_POST['source'] ?? ''
            );

        if (
            !$post_id ||
            get_post_type($post_id) !== 'post'
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'Post tidak valid.'
                ]
            );
        }

        if (
            !current_user_can(
                'edit_post',
                $post_id
            )
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'Tidak memiliki izin mengedit post ini.'
                ],
                403
            );
        }

        if (
            trim($raw) === ''
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'Artikel sumber masih kosong.'
                ]
            );
        }

        /*
         * PARSE.
         */
        $fields =
            $this->parse($raw);

        /*
         * VALIDASI.
         */
        if ($this->import_setting_enabled('title') && $fields['TITLE'] === '') {
            wp_send_json_error(['message' => 'Format artikel tidak lengkap. TITLE wajib tersedia saat pengaturan Judul aktif.']);
        }
        if ($this->import_setting_enabled('category') && $fields['CATEGORY'] === '') {
            wp_send_json_error(['message' => 'Format artikel tidak lengkap. CATEGORY wajib tersedia saat pengaturan Kategori aktif.']);
        }
        if ($this->import_setting_enabled('content') && $fields['CONTENT'] === '') {
            wp_send_json_error(['message' => 'Format artikel tidak lengkap. CONTENT wajib tersedia saat pengaturan Konten aktif.']);
        }

        /*
         * CATEGORY.
         */
        $category_ids = [];
        if ($this->import_setting_enabled('category')) {
            $missing_categories = [];
            $category_ids = $this->category_ids($fields['CATEGORY'], $missing_categories);
            if (!empty($missing_categories)) {
                wp_send_json_error(['message' => 'Kategori tidak ditemukan: ' . implode(', ', $missing_categories) . '. Tidak ada kategori baru yang dibuat.']);
            }
            if (empty($category_ids)) {
                wp_send_json_error(['message' => 'Kategori tidak berhasil ditemukan.']);
            }
        }

        /*
         * TAG.
         */
        $tag_ids = [];
        if ($this->import_setting_enabled('tag')) {
            $missing_tags = [];
            $tag_ids = $this->tag_ids($fields['TAGS'], $missing_tags);
        }

        /*
         * CONTENT.
         */
        $final_content = '';
        if ($this->import_setting_enabled('content')) {
            $final_content = $this->prepare_content($fields['CONTENT']);
            if (trim($final_content) === '') {
                wp_send_json_error(['message' => 'Isi artikel kosong atau gagal dikonversi menjadi Gutenberg blocks.']);
            }
        }

        /*
         * DATA POST.
         */
        $title = sanitize_text_field($fields['TITLE']);
        $description = sanitize_textarea_field(wp_strip_all_tags($fields['DESCRIPTION']));
        $focus_keyphrase = sanitize_text_field($fields['FOCUS_KEYPHRASE']);

        /*
         * SIMPAN POST.
         */
        $post_data = ['ID' => $post_id];
        if ($this->import_setting_enabled('title')) $post_data['post_title'] = $title;
        if ($this->import_setting_enabled('excerpt')) $post_data['post_excerpt'] = $description;
        if ($this->import_setting_enabled('content')) $post_data['post_content'] = $final_content;

        $updated_post = wp_update_post($post_data, true);
        if (is_wp_error($updated_post)) {
            wp_send_json_error(['message' => 'Gagal menyimpan artikel: ' . $updated_post->get_error_message()]);
        }

        /*
         * CATEGORY.
         */
        if ($this->import_setting_enabled('category')) {
            $category_result = wp_set_post_categories($post_id, $category_ids, false);
            if (is_wp_error($category_result)) {
                wp_send_json_error(['message' => 'Kategori gagal diterapkan: ' . $category_result->get_error_message()]);
            }
        }

        /*
         * TAG.
         */
        if ($this->import_setting_enabled('tag')) {
            $tag_result = wp_set_post_terms($post_id, $tag_ids, 'post_tag', false);
            if (is_wp_error($tag_result)) {
                wp_send_json_error(['message' => 'Tag gagal diterapkan: ' . $tag_result->get_error_message()]);
            }
        }

        /*
         * YOAST FOCUS KEYPHRASE.
         */
        if ($this->import_setting_enabled('focus') && $focus_keyphrase !== '') {

            update_post_meta(
                $post_id,
                '_yoast_wpseo_focuskw',
                $focus_keyphrase
            );

        } elseif ($this->import_setting_enabled('focus')) {
            delete_post_meta($post_id, '_yoast_wpseo_focuskw');
        }

        /*
         * YOAST META DESCRIPTION.
         *
         * DESCRIPTION digunakan otomatis
         * sebagai meta description.
         */
        if ($this->import_setting_enabled('excerpt') && $description !== '') {

            update_post_meta(
                $post_id,
                '_yoast_wpseo_metadesc',
                $description
            );

        } elseif ($this->import_setting_enabled('excerpt')) {
            delete_post_meta($post_id, '_yoast_wpseo_metadesc');
        }

        /*
         * Bersihkan cache.
         */
        clean_post_cache(
            $post_id
        );

        /*
         * Verifikasi.
         */
        $saved_post =
            get_post(
                $post_id
            );

        if (
            !$saved_post
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'Artikel tersimpan tetapi gagal diverifikasi.'
                ]
            );
        }

        $saved_content =
            $saved_post->post_content;

        if (
            trim($saved_content) === ''
        ) {

            wp_send_json_error(
                [
                    'message' =>
                        'CONTENT gagal tersimpan ke database.'
                ]
            );
        }

        /*
         * Verifikasi meta.
         */
        $saved_focus =
            get_post_meta(
                $post_id,
                '_yoast_wpseo_focuskw',
                true
            );

        $saved_metadesc =
            get_post_meta(
                $post_id,
                '_yoast_wpseo_metadesc',
                true
            );

        /*
         * RESPONSE FINAL.
         */
        wp_send_json_success(
            [
                'message' =>
                    'Artikel berhasil diterapkan.',

                'title' =>
                    $title,

                'description' =>
                    $description,

                'content' =>
                    $saved_content,

                'contentLength' =>
                    strlen(
                        $saved_content
                    ),

                'categories' =>
                    $category_ids,

                'tags' =>
                    $tag_ids,

                'missingTags' =>
                    $missing_tags,

                'focus' =>
                    $saved_focus,

                'metaDescription' =>
                    $saved_metadesc,

                'postId' =>
                    $post_id,
            ]
        );
    }
}

new JapurSuite_JAPI_V253();