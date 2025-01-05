<?php
/*
Plugin Name: Manual Reviews for WooCommerce
Description: Permite crear y editar manualmente reseñas en WooCommerce, incluyendo imágenes y fecha.
Version: 1.6
Author: Tu Nombre
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evitar acceso directo.
}

// Añadir subpágina al menú de Productos
add_action( 'admin_menu', 'manual_reviews_add_submenu' );

function manual_reviews_add_submenu() {
    add_submenu_page(
        'edit.php?post_type=product', // Menú principal de Productos
        'Gestionar Reseñas',          // Título de la página
        'Reseñas Manuales',           // Título del menú
        'manage_woocommerce',         // Capacidad requerida
        'manual-reviews',             // Slug de la página
        'manual_reviews_subpage'      // Función para renderizar la página
    );
}

// Renderizar la página personalizada
function manual_reviews_subpage() {
    ?>
    <div class="wrap woocommerce">
        <h1>Añadir una Reseña Manualmente</h1>
        <form id="manual-review-form" method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'manual_review_nonce', 'manual_review_nonce_field' ); ?>
            
            <p>
                <label for="product_id">Seleccionar Producto:</label><br>
                <?php
                // Obtener todos los productos
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                );
                $products = get_posts( $args );

                // Crear el desplegable con los productos
                ?>
                <select id="product_id" name="product_id" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ( $products as $product_id ) : ?>
                        <option value="<?php echo esc_attr( $product_id ); ?>">
                            <?php echo esc_html( get_the_title( $product_id ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="reviewer_name">Nombre del Reseñador:</label><br>
                <input type="text" id="reviewer_name" name="reviewer_name">
            </p>
            <p>
                <label for="review_content">Contenido de la Reseña:</label><br>
                <textarea id="review_content" name="review_content" rows="4" required></textarea>
            </p>
            <p>
                <label for="rating">Calificación (1-5):</label><br>
                <input type="number" id="rating" name="rating" min="1" max="5" required>
            </p>
            <p>
                <label for="review_date">Fecha de la Reseña:</label><br>
                <input type="date" id="review_date" name="review_date">
            </p>
            <p>
                <label for="review_image">Subir Imagen:</label><br>
                <input type="file" id="review_image" name="review_image" accept="image/*">
            </p>
            <p>
                <button type="submit" class="button button-primary">Añadir Reseña</button>
            </p>
        </form>
        <?php handle_manual_review_submission(); ?>
    </div>
    <?php
}

// Procesar y guardar las reseñas
function handle_manual_review_submission() {
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['manual_review_nonce_field'] ) && wp_verify_nonce( $_POST['manual_review_nonce_field'], 'manual_review_nonce' ) ) {
        $product_id = intval( $_POST['product_id'] );
        
        // Si no se proporciona nombre de revisor, asignar "Anónimo"
        $reviewer_name = ! empty( $_POST['reviewer_name'] ) ? sanitize_text_field( $_POST['reviewer_name'] ) : 'Anonymous';
        
        $review_content = sanitize_textarea_field( $_POST['review_content'] );
        $rating = intval( $_POST['rating'] );
        $review_date = ! empty( $_POST['review_date'] ) ? sanitize_text_field( $_POST['review_date'] ) : current_time( 'mysql' );

        // Subir imagen
        $image_url = '';
        if ( isset( $_FILES['review_image'] ) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['review_image'], [ 'test_form' => false ] );
            if ( isset( $uploaded_file['url'] ) ) {
                $image_url = $uploaded_file['url'];
            }
        }

        // Crear el comentario
        $comment_data = [
            'comment_post_ID' => $product_id,
            'comment_author' => $reviewer_name,
            'comment_content' => $review_content,
            'comment_type' => 'review',
            'comment_date' => $review_date,
            'comment_approved' => 1,
        ];

        $comment_id = wp_insert_comment( $comment_data );

        if ( $comment_id ) {
            // Añadir la calificación
            update_comment_meta( $comment_id, 'rating', $rating );

            // Añadir la URL de la imagen como metadata
            if ( $image_url ) {
                update_comment_meta( $comment_id, 'review_image', $image_url );
            }

            // Marcar como "Verified Owner"
            update_comment_meta( $comment_id, 'verified', 1 );

            echo '<div class="notice notice-success is-dismissible"><p>Reseña creada exitosamente.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error al crear la reseña.</p></div>';
        }
    }
}
