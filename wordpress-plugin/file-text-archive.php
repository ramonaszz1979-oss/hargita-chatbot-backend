<?php
/**
 * Plugin Name: File Text Archive
 * Description: Upload arbitrary documents, detect their language, and archive extracted text copies.
 * Version: 1.0.0
 * Author: ChatGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-file-text-archive.php';

function file_text_archive_run() {
    $plugin = new File_Text_Archive();
    register_activation_hook( __FILE__, array( $plugin, 'ensure_archive_directory' ) );
    $plugin->run();
}
file_text_archive_run();
