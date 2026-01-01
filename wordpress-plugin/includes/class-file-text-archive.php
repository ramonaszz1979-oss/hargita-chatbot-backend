<?php
class File_Text_Archive {
    private $option_key = 'file_text_archive_items';
    private $archive_dir;
    private $archive_url;
    private $admin_page = 'file-text-archive';
    private $tools_page = 'file-text-archive-tools';

    public function __construct() {
        $upload_dir        = wp_upload_dir();
        $this->archive_dir = trailingslashit( $upload_dir['basedir'] ) . 'site-text-archives/file';
        $this->archive_url = trailingslashit( $upload_dir['baseurl'] ) . 'site-text-archives/file';
    }

    public function run() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_fta_upload', array( $this, 'handle_upload' ) );
        add_action( 'admin_post_nopriv_fta_upload', array( $this, 'handle_upload' ) );
        add_action( 'admin_post_fta_delete', array( $this, 'handle_delete' ) );
        add_action( 'admin_post_nopriv_fta_delete', array( $this, 'handle_delete' ) );
        add_shortcode( 'file_text_archive', array( $this, 'render_shortcode' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'File Text Archive', 'file-text-archive' ),
            __( 'Text Archive', 'file-text-archive' ),
            'manage_options',
            $this->admin_page,
            array( $this, 'render_admin_page' ),
            'dashicons-media-document'
        );

        add_management_page(
            __( 'File Text Archive', 'file-text-archive' ),
            __( 'File Text Archive', 'file-text-archive' ),
            'upload_files',
            $this->tools_page,
            array( $this, 'render_admin_page' )
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }

        $this->ensure_archive_directory();
        $items   = get_option( $this->option_key, array() );
        $status  = isset( $_GET['fta_status'] ) ? sanitize_text_field( wp_unslash( $_GET['fta_status'] ) ) : '';
        $message = '';

        if ( 'uploaded' === $status ) {
            $message = __( 'Document uploaded and archived successfully.', 'file-text-archive' );
        } elseif ( 'deleted' === $status ) {
            $message = __( 'Document and archived text removed.', 'file-text-archive' );
        } elseif ( 'error' === $status && isset( $_GET['fta_message'] ) ) {
            $message = sanitize_text_field( wp_unslash( $_GET['fta_message'] ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'File Text Archive', 'file-text-archive' ); ?></h1>
            <div class="notice notice-info inline" style="padding: 10px;">
                <p style="margin-top: 0;"><strong><?php esc_html_e( 'Shortcode', 'file-text-archive' ); ?>:</strong> <?php esc_html_e( 'Place this shortcode in any page or post to embed the uploader and list:', 'file-text-archive' ); ?></p>
                <input type="text" class="regular-text" readonly value="[file_text_archive]" onclick="this.select();" aria-label="<?php esc_attr_e( 'File Text Archive shortcode', 'file-text-archive' ); ?>" />
            </div>
            <?php if ( $message ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <h2><?php esc_html_e( 'Upload a document', 'file-text-archive' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'fta_upload' ); ?>
                <input type="hidden" name="action" value="fta_upload" />
                <input type="hidden" name="fta_return" value="<?php echo esc_url( $this->current_page_url() ); ?>" />
                <input type="file" name="fta_file" required />
                <?php submit_button( __( 'Upload and archive', 'file-text-archive' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Uploaded documents', 'file-text-archive' ); ?></h2>
            <?php echo $this->render_archive_table( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    private function render_archive_table( $items, $table_class = 'widefat fixed' ) {
        ob_start();
        ?>
        <table class="<?php echo esc_attr( $table_class ); ?>" cellspacing="0">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Original name', 'file-text-archive' ); ?></th>
                    <th><?php esc_html_e( 'Language', 'file-text-archive' ); ?></th>
                    <th><?php esc_html_e( 'Uploaded', 'file-text-archive' ); ?></th>
                    <th><?php esc_html_e( 'Original file', 'file-text-archive' ); ?></th>
                    <th><?php esc_html_e( 'Archived text', 'file-text-archive' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'file-text-archive' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $items ) ) : ?>
                <tr><td colspan="6"><?php esc_html_e( 'No documents uploaded yet.', 'file-text-archive' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td><?php echo esc_html( $item['original_name'] ); ?></td>
                        <td><?php echo esc_html( $item['language'] ); ?></td>
                        <td><?php echo esc_html( $item['created_at'] ); ?></td>
                        <td><a href="<?php echo esc_url( $item['file_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download', 'file-text-archive' ); ?></a></td>
                        <td><a href="<?php echo esc_url( $item['txt_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download TXT', 'file-text-archive' ); ?></a></td>
                        <td>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                <?php wp_nonce_field( 'fta_delete_' . $item['id'] ); ?>
                                <input type="hidden" name="action" value="fta_delete" />
                                <input type="hidden" name="fta_id" value="<?php echo esc_attr( $item['id'] ); ?>" />
                                <input type="hidden" name="fta_return" value="<?php echo esc_url( $this->current_page_url() ); ?>" />
                                <?php submit_button( __( 'Delete', 'file-text-archive' ), 'delete', 'submit', false ); ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php

        return ob_get_clean();
    }

    public function render_shortcode() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return '<p>' . esc_html__( 'You need permission to upload files to use the archive.', 'file-text-archive' ) . '</p>';
        }

        $this->ensure_archive_directory();
        $items = get_option( $this->option_key, array() );

        ob_start();
        ?>
        <div class="file-text-archive-shortcode">
            <h2><?php esc_html_e( 'Upload a document', 'file-text-archive' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'fta_upload' ); ?>
                <input type="hidden" name="action" value="fta_upload" />
                <input type="hidden" name="fta_return" value="<?php echo esc_url( $this->current_page_url() ); ?>" />
                <input type="file" name="fta_file" required />
                <?php submit_button( __( 'Upload and archive', 'file-text-archive' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Uploaded documents', 'file-text-archive' ); ?></h2>
            <?php echo $this->render_archive_table( $items, 'widefat fixed striped' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php

        return ob_get_clean();
    }

    public function handle_upload() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( __( 'You do not have permission to upload files.', 'file-text-archive' ) );
        }

        check_admin_referer( 'fta_upload' );

        $return_url = isset( $_POST['fta_return'] ) ? esc_url_raw( wp_unslash( $_POST['fta_return'] ) ) : '';

        if ( empty( $_FILES['fta_file']['tmp_name'] ) ) {
            $this->redirect_with_message( 'error', __( 'No file received.', 'file-text-archive' ), $return_url );
        }

        $this->ensure_archive_directory();

        $uploaded = wp_handle_upload(
            $_FILES['fta_file'],
            array(
                'test_form' => false,
                'test_type' => false,
            )
        );

        if ( isset( $uploaded['error'] ) ) {
            $this->redirect_with_message( 'error', $uploaded['error'], $return_url );
        }

        $text_content = $this->extract_text( $uploaded['file'] );
        $language     = $this->detect_language( $text_content );

        $txt_filename = sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) . '-' . time() . '.txt' );
        $txt_path     = trailingslashit( $this->archive_dir ) . $txt_filename;
        $txt_url      = trailingslashit( $this->archive_url ) . $txt_filename;

        file_put_contents( $txt_path, $text_content );

        $items   = get_option( $this->option_key, array() );
        $items[] = array(
            'id'            => uniqid( 'fta_', true ),
            'original_name' => sanitize_file_name( wp_unslash( $_FILES['fta_file']['name'] ) ),
            'file_path'     => $uploaded['file'],
            'file_url'      => $uploaded['url'],
            'language'      => $language,
            'txt_path'      => $txt_path,
            'txt_url'       => $txt_url,
            'created_at'    => current_time( 'mysql' ),
        );

        update_option( $this->option_key, $items );

        $this->redirect_with_message( 'uploaded', '', $return_url );
    }

    public function handle_delete() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( __( 'You do not have permission to delete files.', 'file-text-archive' ) );
        }

        $fta_id = isset( $_POST['fta_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fta_id'] ) ) : '';
        $return_url = isset( $_POST['fta_return'] ) ? esc_url_raw( wp_unslash( $_POST['fta_return'] ) ) : '';

        check_admin_referer( 'fta_delete_' . $fta_id );

        $items = get_option( $this->option_key, array() );

        foreach ( $items as $index => $item ) {
            if ( $item['id'] === $fta_id ) {
                if ( ! empty( $item['file_path'] ) ) {
                    wp_delete_file( $item['file_path'] );
                }

                if ( ! empty( $item['txt_path'] ) ) {
                    wp_delete_file( $item['txt_path'] );
                }

                unset( $items[ $index ] );
                update_option( $this->option_key, array_values( $items ) );
                $this->redirect_with_message( 'deleted', '', $return_url );
                return;
            }
        }

        $this->redirect_with_message( 'error', __( 'Item not found.', 'file-text-archive' ), $return_url );
    }

    public function ensure_archive_directory() {
        if ( ! file_exists( $this->archive_dir ) ) {
            wp_mkdir_p( $this->archive_dir );
        }
    }

    private function redirect_with_message( $status, $message = '', $return_url = '' ) {
        $default_url = add_query_arg( array( 'page' => $this->admin_page ), admin_url( 'admin.php' ) );
        $target_url  = $return_url ? wp_validate_redirect( $return_url, $default_url ) : $default_url;

        $args = array( 'fta_status' => $status );
        if ( $message ) {
            $args['fta_message'] = $message;
        }

        wp_safe_redirect( add_query_arg( $args, $target_url ) );
        exit;
    }

    private function current_page_url() {
        if ( is_admin() ) {
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : $this->admin_page; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return add_query_arg( array( 'page' => $page ), admin_url( 'admin.php' ) );
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

        return $request_uri ? home_url( $request_uri ) : add_query_arg( array( 'page' => $this->admin_page ), admin_url( 'admin.php' ) );
    }

    private function extract_text( $file_path ) {
        $extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        $content   = '';

        switch ( $extension ) {
            case 'txt':
            case 'md':
            case 'csv':
            case 'json':
            case 'xml':
                $content = file_get_contents( $file_path );
                break;
            case 'docx':
                $content = $this->extract_docx( $file_path );
                break;
            case 'pdf':
                $content = $this->extract_pdf( $file_path );
                break;
            default:
                $content = file_get_contents( $file_path );
        }

        $content = wp_strip_all_tags( (string) $content );
        $content = preg_replace( '/\s+/', ' ', $content );

        return trim( $content ) ? trim( $content ) : __( 'No extractable text was found in this document.', 'file-text-archive' );
    }

    private function extract_docx( $file_path ) {
        $zip = new ZipArchive();
        if ( true === $zip->open( $file_path ) ) {
            $index = $zip->locateName( 'word/document.xml' );
            if ( false !== $index ) {
                $xml = $zip->getFromIndex( $index );
                $zip->close();
                return $this->strip_xml_text( $xml );
            }
            $zip->close();
        }

        return '';
    }

    private function extract_pdf( $file_path ) {
        if ( $this->command_exists( 'pdftotext' ) ) {
            $tmp_file = wp_tempnam( 'fta_pdf' );
            shell_exec( escapeshellcmd( 'pdftotext' ) . ' ' . escapeshellarg( $file_path ) . ' ' . escapeshellarg( $tmp_file ) );
            $content = file_get_contents( $tmp_file );
            unlink( $tmp_file );
            return $content;
        }

        return __( 'PDF text extraction is unavailable on this server. Install the "pdftotext" utility (poppler) to enable automatic extraction.', 'file-text-archive' );
    }

    private function strip_xml_text( $xml ) {
        $xml = preg_replace( '/<w:p[^>]*>/', "\n", $xml );
        $xml = strip_tags( $xml );
        return html_entity_decode( $xml, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

    private function detect_language( $text ) {
        $text       = strtolower( $text );
        $stopwords  = $this->stopword_map();
        $best_lang  = __( 'Unknown', 'file-text-archive' );
        $best_score = 0;

        foreach ( $stopwords as $lang => $words ) {
            $score = 0;
            foreach ( $words as $word ) {
                if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/u', $text ) ) {
                    $score++;
                }
            }

            if ( $score > $best_score ) {
                $best_score = $score;
                $best_lang  = $lang;
            }
        }

        return $best_lang;
    }

    private function stopword_map() {
        return array(
            'English'   => array( 'the', 'and', 'of', 'to', 'in', 'that', 'is', 'for', 'on', 'with', 'as', 'are' ),
            'Hungarian' => array( 'és', 'hogy', 'nem', 'van', 'az', 'egy', 'meg', 'mert', 'mint', 'vagy', 'ami', 'akkor' ),
            'German'    => array( 'der', 'die', 'und', 'den', 'von', 'ist', 'im', 'dass', 'nicht', 'ein', 'zu', 'mit' ),
            'French'    => array( 'le', 'de', 'et', 'la', 'les', 'des', 'du', 'un', 'en', 'que', 'pour', 'est' ),
            'Spanish'   => array( 'el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'ser', 'se', 'no', 'por' ),
            'Italian'   => array( 'il', 'la', 'e', 'di', 'che', 'in', 'un', 'per', 'del', 'è', 'si', 'una' ),
        );
    }

    private function command_exists( $command ) {
        $which = shell_exec( 'command -v ' . escapeshellarg( $command ) );
        return ! empty( $which );
    }
}
