(function(wp){

    const { registerPlugin } = wp.plugins;

    const {
        PluginDocumentSettingPanel
    } = wp.editPost;

    const {
        createElement: el,
        useState
    } = wp.element;

    const {
        TextareaControl,
        Button,
        Notice
    } = wp.components;

    const {
        select,
        dispatch
    } = wp.data;


    /*
     * ============================================================
     * JAPI 2.5.4
     * ============================================================
     *
     * Perbaikan:
     *
     * 1. DESCRIPTION
     *    -> WordPress Excerpt
     *    -> Yoast SEO Meta Description
     *
     * 2. FOCUS_KEYPHRASE
     *    -> Yoast SEO Focus Keyphrase
     *
     * 3. CONTENT
     *    -> Gutenberg blocks
     *
     * PHP menyimpan metadata ke database.
     * JS di sini juga mencoba mengisi Yoast editor store
     * agar field Yoast langsung terlihat tanpa reload.
     */


    /*
     * ============================================================
     * HELPER: UPDATE YOAST STORE
     * ============================================================
     *
     * Yoast menggunakan store:
     *
     *     yoast-seo/editor
     *
     * Sedangkan Gutenberg menggunakan:
     *
     *     core/editor
     *
     * Karena keduanya tidak selalu otomatis sinkron,
     * kita update keduanya.
     */
    function updateYoastStore(data) {

        const focus =
            typeof data.focus === 'string'
                ? data.focus
                : '';

        const description =
            typeof data.metaDescription === 'string'
                ? data.metaDescription
                : '';


        /*
         * Pastikan store Yoast tersedia.
         */
        let yoastSelect = null;
        let yoastDispatch = null;

        try {

            yoastSelect =
                select('yoast-seo/editor');

            yoastDispatch =
                dispatch('yoast-seo/editor');

        } catch (error) {

            console.warn(
                'JAPI: Yoast editor store tidak tersedia.',
                error
            );

            return false;
        }


        if (
            !yoastDispatch
        ) {

            console.warn(
                'JAPI: Yoast dispatch tidak tersedia.'
            );

            return false;
        }


        /*
         * --------------------------------------------------------
         * META DESCRIPTION
         * --------------------------------------------------------
         *
         * Yoast Snippet Editor menggunakan:
         *
         * updateData({
         *     description: '...'
         * })
         */
        if (
            typeof yoastDispatch.updateData === 'function'
        ) {

            try {

                yoastDispatch.updateData({
                    description:
                        description
                });

            } catch (error) {

                console.warn(
                    'JAPI: gagal mengisi Yoast Meta Description.',
                    error
                );
            }
        }


        /*
         * --------------------------------------------------------
         * FOCUS KEYPHRASE
         * --------------------------------------------------------
         *
         * Versi Yoast berbeda dapat mempunyai action
         * yang berbeda. Kita coba beberapa API yang tersedia.
         */

        let focusUpdated = false;


        /*
         * API yang tersedia pada sebagian versi Yoast.
         */
        if (
            typeof yoastDispatch.setFocusKeyword === 'function'
        ) {

            try {

                yoastDispatch.setFocusKeyword(
                    focus
                );

                focusUpdated = true;

            } catch (error) {

                console.warn(
                    'JAPI: setFocusKeyword gagal.',
                    error
                );
            }
        }


        /*
         * Beberapa versi/store menggunakan updateData.
         *
         * Jangan menganggap focuskw selalu diterima oleh
         * updateData, tetapi kita coba bila API tersedia.
         */
        if (
            !focusUpdated &&
            typeof yoastDispatch.updateData === 'function'
        ) {

            try {

                yoastDispatch.updateData({
                    focusKeyword:
                        focus
                });

                focusUpdated = true;

            } catch (error) {

                console.warn(
                    'JAPI: updateData focusKeyword gagal.',
                    error
                );
            }
        }


        /*
         * Jika Yoast store berhasil ditemukan,
         * anggap metadata sudah dicoba disinkronkan.
         */
        return true;
    }


    /*
     * ============================================================
     * HELPER: UPDATE CORE EDITOR META
     * ============================================================
     *
     * Ini tetap dilakukan karena PHP menggunakan:
     *
     * _yoast_wpseo_focuskw
     * _yoast_wpseo_metadesc
     *
     * dan Yoast pada kondisi tertentu membaca metadata ini
     * dari Gutenberg.
     */
    function updateCoreEditorMeta(data) {

        const focus =
            typeof data.focus === 'string'
                ? data.focus
                : '';

        const description =
            typeof data.metaDescription === 'string'
                ? data.metaDescription
                : '';


        try {

            const editor =
                select('core/editor');

            const currentMeta =
                editor.getEditedPostAttribute('meta') || {};


            const nextMeta =
                Object.assign(
                    {},
                    currentMeta,
                    {
                        _yoast_wpseo_focuskw:
                            focus,

                        _yoast_wpseo_metadesc:
                            description
                    }
                );


            dispatch(
                'core/editor'
            ).editPost({
                meta:
                    nextMeta
            });


            return true;

        } catch (error) {

            console.warn(
                'JAPI: gagal memperbarui core/editor meta.',
                error
            );

            return false;
        }
    }


    /*
     * ============================================================
     * HELPER: UPDATE POST DATA
     * ============================================================
     */
    function updatePostData(data) {

        dispatch(
            'core/editor'
        ).editPost({

            title:
                data.title || '',

            excerpt:
                data.description || '',

            categories:
                data.categories || [],

            tags:
                data.tags || []

        });
    }


    /*
     * ============================================================
     * PANEL
     * ============================================================
     */
    function Panel() {

        const [value, setValue] =
            useState('');

        const [busy, setBusy] =
            useState(false);

        const [notice, setNotice] =
            useState(null);


        const postId =
            select(
                'core/editor'
            ).getCurrentPostId();


        /*
         * ========================================================
         * APPLY / IMPORT
         * ========================================================
         */
        const apply = () => {

            if (
                busy ||
                !value.trim()
            ) {

                return;
            }


            setBusy(true);
            setNotice(null);


            const data =
                new FormData();


            data.append(
                'action',
                'japi_v25_import'
            );


            data.append(
                'nonce',
                JAPI25.nonce
            );


            data.append(
                'postId',
                postId
            );


            data.append(
                'source',
                value
            );


            fetch(
                JAPI25.ajaxUrl,
                {
                    method:
                        'POST',

                    body:
                        data,

                    credentials:
                        'same-origin'
                }
            )


            /*
             * ====================================================
             * RESPONSE
             * ====================================================
             */
            .then(
                async response => {

                    const text =
                        await response.text();

                    let result;


                    try {

                        result =
                            JSON.parse(text);

                    } catch (error) {

                        console.error(
                            'JAPI RESPONSE:',
                            text
                        );

                        throw new Error(
                            'Server mengembalikan respons yang tidak valid.'
                        );
                    }


                    if (
                        !result.success
                    ) {

                        throw new Error(
                            result.data?.message ||
                            'Gagal memproses artikel.'
                        );
                    }


                    return result;
                }
            )


            /*
             * ====================================================
             * PROSES HASIL
             * ====================================================
             */
            .then(
                result => {

                    const data =
                        result.data || {};


                    /*
                     * CONTENT WAJIB ADA.
                     */
                    if (
                        !data.content ||
                        !data.content.trim()
                    ) {

                        throw new Error(
                            'CONTENT kosong dari server.'
                        );
                    }


                    /*
                     * =================================================
                     * PARSE GUTENBERG
                     * =================================================
                     */
                    let blocks;


                    try {

                        blocks =
                            wp.blocks.parse(
                                data.content
                            );

                    } catch (error) {

                        console.error(
                            'Gutenberg parse error:',
                            error
                        );

                        throw new Error(
                            'Konten diterima tetapi gagal dibaca Gutenberg.'
                        );
                    }


                    if (
                        !blocks ||
                        !blocks.length
                    ) {

                        throw new Error(
                            'Konten diterima tetapi tidak menghasilkan blok Gutenberg.'
                        );
                    }


                    /*
                     * =================================================
                     * MASUKKAN CONTENT KE EDITOR
                     * =================================================
                     */
                    dispatch(
                        'core/block-editor'
                    ).resetBlocks(
                        blocks
                    );


                    /*
                     * =================================================
                     * UPDATE JUDUL / EXCERPT / CATEGORY / TAG
                     * =================================================
                     */
                    updatePostData(
                        data
                    );


                    /*
                     * =================================================
                     * UPDATE CORE EDITOR META
                     * =================================================
                     *
                     * Ini menjaga metadata Yoast di Gutenberg.
                     */
                    updateCoreEditorMeta(
                        data
                    );


                    /*
                     * =================================================
                     * UPDATE YOAST STORE
                     * =================================================
                     *
                     * Ini bagian penting v2.5.4.
                     *
                     * Versi sebelumnya hanya:
                     *
                     * core/editor -> editPost()
                     *
                     * tetapi field Meta Description Yoast
                     * menggunakan store:
                     *
                     * yoast-seo/editor
                     */
                    updateYoastStore(
                        data
                    );


                    /*
                     * =================================================
                     * TEXTAREA
                     * =================================================
                     *
                     * Hanya dikosongkan jika proses benar-benar sukses.
                     */
                    setValue('');


                    /*
                     * =================================================
                     * PESAN
                     * =================================================
                     */
                    let message =
                        'Artikel berhasil diterapkan. ' +
                        'Konten sudah masuk sebagai blok Gutenberg.';


                    if (
                        data.metaDescription
                    ) {

                        message +=
                            ' Meta Description Yoast sudah dikirim ke editor.';
                    }


                    if (
                        data.focus
                    ) {

                        message +=
                            ' Frasa kunci utama Yoast sudah dikirim ke editor.';
                    }


                    if (
                        data.missingTags &&
                        data.missingTags.length
                    ) {

                        message +=
                            ' Tag yang tidak ditemukan dilewati: ' +
                            data.missingTags.join(', ') +
                            '.';
                    }


                    if (window.JapurSuiteToast) {
                        window.JapurSuiteToast.show(message, 'success');
                    } else {
                        setNotice({ status: 'success', msg: message });
                    }

                }
            )


            /*
             * ====================================================
             * ERROR
             * ====================================================
             */
            .catch(
                error => {

                    console.error(
                        'JAPI ERROR:',
                        error
                    );


                    /*
                     * Jangan mengosongkan textarea.
                     */
                    const message =
                        error.message ||
                        'Proses gagal.';
                    if (window.JapurSuiteToast) {
                        window.JapurSuiteToast.show(message, 'error');
                    } else {
                        setNotice({ status: 'error', msg: message });
                    }

                }
            )


            /*
             * ====================================================
             * FINALLY
             * ====================================================
             */
            .finally(
                () => {

                    setBusy(false);

                }
            );
        };


        /*
         * ========================================================
         * UI
         * ========================================================
         */
        return el(

            PluginDocumentSettingPanel,

            {
                name:
                    'japi-v254-panel',

                title:
                    'Auto Artikel',

                className:
                    'japi-v25-panel'
            },


            /*
             * NOTICE
             */
            notice &&
                el(
                    Notice,
                    {
                        status:
                            notice.status,

                        isDismissible:
                            true,

                        onRemove:
                            () =>
                                setNotice(null)
                    },

                    notice.msg
                ),


            /*
             * TEXTAREA
             */
            el(
                TextareaControl,
                {
                    label:
                        'Tempel artikel terstruktur',

                    help:
                        'Mendukung format satu baris dan dua baris. Semua isi setelah [CONTENT] dianggap sebagai konten artikel.',

                    value:
                        value,

                    onChange:
                        setValue,

                    rows:
                        18
                }
            ),


            /*
             * BUTTON
             */
            el(
                Button,
                {
                    variant:
                        'primary',

                    onClick:
                        apply,

                    disabled:
                        busy ||
                        !value.trim(),

                    isBusy:
                        busy,

                    className:
                        'japi-v25-process-button'
                },

                busy
                    ? 'Memproses...'
                    : 'Deteksi & Terapkan'
            )

        );
    }


    /*
     * ============================================================
     * REGISTER PLUGIN
     * ============================================================
     */
    registerPlugin(
        'japi-v254',
        {
            render:
                Panel,

            icon:
                null
        }
    );


})(window.wp);