<?php
/**
 * Plugin Name: ToVision Portfolio Creator
 * Description: Een plugin om portfolio posts aan te maken met automatische WebP conversie.
 * Version: 1.2.3
 * Author: ToVision
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ToVision_Portfolio_Creator {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_post_tovision_create_portfolio', array( $this, 'handle_portfolio_creation' ) );
        add_filter( 'template_include', array( $this, 'load_portfolio_template' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_portfolio_assets' ) );
    }

    public function load_portfolio_template( $template ) {
        if ( is_singular( 'portfolio' ) ) {
            $plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-portfolio.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function enqueue_portfolio_assets() {
        if ( is_singular( 'portfolio' ) ) {
            wp_enqueue_style( 'tovision-portfolio-style', plugin_dir_url( __FILE__ ) . 'assets/portfolio-style.css', array(), '1.0' );
            wp_enqueue_script( 'tovision-portfolio-script', plugin_dir_url( __FILE__ ) . 'assets/portfolio-script.js', array(), '1.0', true );
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Portfolio Creator',
            'Portfolio Creator',
            'manage_options',
            'tovision-portfolio-creator',
            array( $this, 'render_admin_page' ),
            'dashicons-art',
            20
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Nieuw Portfolio Item Aanmaken</h1>
            <?php 
            if ( isset( $_GET['success'] ) ) {
                echo '<div class="notice notice-success"><p>Portfolio item succesvol aangemaakt!</p></div>';
            }
            if ( isset( $_GET['error'] ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $_GET['error'] ) . '</p></div>';
            }
            ?>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tovision_create_portfolio">
                <?php wp_nonce_field( 'tovision_create_portfolio_nonce', 'tovision_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title">Titel</label></th>
                        <td><input type="text" name="title" id="title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="date">Datum</label></th>
                        <td><input type="date" name="date" id="date" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="location">Locatie</label></th>
                        <td><input type="text" name="location" id="location" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="content">Beschrijving</label></th>
                        <td><?php wp_editor( '', 'content', array( 'textarea_name' => 'content', 'media_buttons' => false, 'textarea_rows' => 10 ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="images">Afbeeldingen (Max 15)</label></th>
                        <td><input type="file" name="images[]" id="images" multiple accept="image/*" required>
                        <p class="description">Selecteer tot 15 afbeeldingen. Deze worden automatisch geconverteerd naar WebP.</p></td>
                    </tr>
                </table>
                <?php submit_button( 'Portfolio Item Aanmaken' ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_portfolio_creation() {
        if ( ! isset( $_POST['tovision_nonce'] ) || ! wp_verify_nonce( $_POST['tovision_nonce'], 'tovision_create_portfolio_nonce' ) ) {
            wp_die( 'Beveiligingsfout' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toestemming' );
        }

        $title = sanitize_text_field( $_POST['title'] );
        $date = sanitize_text_field( $_POST['date'] );
        $location = sanitize_text_field( $_POST['location'] );
        $content = wp_kses_post( $_POST['content'] );
        $images = $_FILES['images'];

        // 1. Upload and Convert Images
        $gallery_ids = $this->process_images( $images );
        if ( is_wp_error( $gallery_ids ) ) {
            wp_redirect( admin_url( 'admin.php?page=tovision-portfolio-creator&error=' . urlencode( $gallery_ids->get_error_message() ) ) );
            exit;
        }

        // 2. Create Post
        $post_id = wp_insert_post( array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_type'     => 'portfolio',
        ) );

        if ( is_wp_error( $post_id ) ) {
             wp_redirect( admin_url( 'admin.php?page=tovision-portfolio-creator&error=' . urlencode( 'Kon post niet aanmaken.' ) ) );
             exit;
        }

        // 3. Update ACF Fields
        if ( function_exists( 'update_field' ) ) {
            // ACF Date Picker expects Ymd format
            $acf_date = str_replace( '-', '', $date );
            update_field( 'datum', $acf_date, $post_id );
            update_field( 'locatie', $location, $post_id );
            update_field( 'gallerij', $gallery_ids, $post_id );
        }

        // Set the first image as featured image if available
        if ( ! empty( $gallery_ids ) ) {
            set_post_thumbnail( $post_id, $gallery_ids[0] );
        }

        wp_redirect( admin_url( 'admin.php?page=tovision-portfolio-creator&success=1' ) );
        exit;
    }

    private function process_images( $files ) {
        $gallery_ids = array();
        
        if ( empty( $files['name'][0] ) ) {
            return $gallery_ids; 
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $count = count( $files['name'] );
        $limit = 15;

        for ( $i = 0; $i < $count; $i++ ) {
            if ( $i >= $limit ) break;

            if ( $files['error'][$i] !== UPLOAD_ERR_OK ) {
                continue;
            }

            // Temporarily organize file array for media_handle_sideload-like processing
            $file = array(
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i]
            );

            // Upload the original file first
            $upload_overrides = array( 'test_form' => false );
            $movefile = wp_handle_upload( $file, $upload_overrides );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $file_path = $movefile['file'];
                $file_type = $movefile['type'];
                
                // Convert to WebP
                $image_editor = wp_get_image_editor( $file_path );
                if ( ! is_wp_error( $image_editor ) ) {
                    $file_info = pathinfo( $file_path );
                    $webp_filename = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
                    
                    $saved = $image_editor->save( $webp_filename, 'image/webp' );
                    
                    if ( ! is_wp_error( $saved ) ) {
                        // Use the WebP file now
                        $file_path = $saved['path'];
                        $file_type = 'image/webp';
                        
                        // Optionally delete original file if you only want WebP
                        if ( $movefile['file'] !== $saved['path'] && file_exists( $movefile['file'] ) ) {
                            unlink( $movefile['file'] );
                        }
                    }
                }

                // Insert into Media Library
                $attachment = array(
                    'post_mime_type' => $file_type,
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attach_id = wp_insert_attachment( $attachment, $file_path );

                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    $gallery_ids[] = $attach_id;
                }
            }
        }

        return $gallery_ids;
    }
}

new ToVision_Portfolio_Creator();
