<?php
/**
 * Plugin Name: Admin Columns Manager
 * Plugin URI: https://github.com/Azanumer/wp-admin-columns-manager
 * Description: Adds the columns WordPress forgot to the posts/pages list — ID, featured-image thumbnail, word count, last modified date and comment count — sortable, with per-column toggles under Settings.
 * Version: 1.0.0
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * Text Domain: wp-admin-columns-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WACM_OPTION', 'wacm_columns' );

/**
 * Column definitions: key => label. These are the five columns we manage.
 */
function wacm_column_definitions() {
    return array(
        'wacm_id'            => __( 'ID', 'wp-admin-columns-manager' ),
        'wacm_thumbnail'     => __( 'Image', 'wp-admin-columns-manager' ),
        'wacm_word_count'    => __( 'Words', 'wp-admin-columns-manager' ),
        'wacm_last_modified' => __( 'Last Modified', 'wp-admin-columns-manager' ),
        'wacm_comments'     => __( 'Comments', 'wp-admin-columns-manager' ),
    );
}

function wacm_default_settings() {
    $enabled = array();
    foreach ( array_keys( wacm_column_definitions() ) as $key ) {
        $enabled[ $key ] = 1;
    }
    return array(
        'post_types' => array( 'post', 'page' ),
        'columns'    => $enabled,
    );
}

function wacm_get_settings() {
    $saved = get_option( WACM_OPTION, array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    $settings = array_merge( wacm_default_settings(), $saved );
    if ( ! is_array( $settings['columns'] ) ) {
        $settings['columns'] = wacm_default_settings()['columns'];
    }
    if ( ! is_array( $settings['post_types'] ) ) {
        $settings['post_types'] = array( 'post', 'page' );
    }
    return $settings;
}

function wacm_enabled_columns() {
    $settings = wacm_get_settings();
    $defs     = wacm_column_definitions();
    $enabled  = array();
    foreach ( $defs as $key => $label ) {
        if ( ! empty( $settings['columns'][ $key ] ) ) {
            $enabled[ $key ] = $label;
        }
    }
    return $enabled;
}

function wacm_managed_post_types() {
    $settings = wacm_get_settings();
    $types    = array();
    foreach ( (array) $settings['post_types'] as $type ) {
        if ( post_type_exists( $type ) ) {
            $types[] = $type;
        }
    }
    return $types;
}

/* ------------------------------------------------------------------ */
/* Add the columns                                                     */
/* ------------------------------------------------------------------ */

/**
 * Insert our columns right after the checkbox column, before the title.
 */
function wacm_add_columns( $columns ) {
    $enabled = wacm_enabled_columns();
    if ( empty( $enabled ) ) {
        return $columns;
    }

    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'cb' === $key ) {
            foreach ( $enabled as $col_key => $col_label ) {
                $new[ $col_key ] = $col_label;
            }
        }
    }
    return $new;
}

/**
 * Fill each managed column's cell for a post.
 */
function wacm_fill_column( $column, $post_id ) {
    switch ( $column ) {
        case 'wacm_id':
            echo esc_html( $post_id );
            break;

        case 'wacm_thumbnail':
            if ( has_post_thumbnail( $post_id ) ) {
                echo get_the_post_thumbnail( $post_id, array( 48, 48 ), array( 'style' => 'max-width:48px;height:auto;' ) );
            } else {
                echo '&mdash;';
            }
            break;

        case 'wacm_word_count':
            $content = get_post_field( 'post_content', $post_id );
            echo esc_html( number_format_i18n( str_word_count( wp_strip_all_tags( $content ) ) ) );
            break;

        case 'wacm_last_modified':
            $modified = get_post_field( 'post_modified', $post_id );
            echo esc_html( $modified ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $modified ) : '&mdash;' );
            break;

        case 'wacm_comments':
            $count = get_comments_number( $post_id );
            echo esc_html( number_format_i18n( $count ) );
            break;
    }
}

/* ------------------------------------------------------------------ */
/* Sorting                                                             */
/* ------------------------------------------------------------------ */

/**
 * Register the sortable columns. Thumbnail is not sortable (no cheap
 * ordering exists for it); the rest sort by a real value.
 */
function wacm_sortable_columns( $columns ) {
    $columns['wacm_id']            = 'ID';
    $columns['wacm_word_count']    = 'wacm_word_count';
    $columns['wacm_last_modified'] = 'modified';
    $columns['wacm_comments']     = 'comment_count';
    return $columns;
}

/**
 * Word count has no native orderby, so flag the query here and rewrite the
 * ORDER BY clause to CHAR_LENGTH(post_content) — longer content ≈ more
 * words, and it sorts correctly where a plain date fallback would not.
 */
function wacm_orderby_word_count( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( 'wacm_word_count' === $query->get( 'orderby' ) ) {
        $query->set( 'wacm_sort_by_word_count', true );
        $query->set( 'orderby', 'none' );
    }
}
add_action( 'pre_get_posts', 'wacm_orderby_word_count' );

function wacm_posts_orderby_word_count( $orderby, $query ) {
    global $wpdb;
    if ( ! $query->get( 'wacm_sort_by_word_count' ) ) {
        return $orderby;
    }
    $direction = ( 'ASC' === strtoupper( $query->get( 'order' ) ) ) ? 'ASC' : 'DESC';
    return "CHAR_LENGTH({$wpdb->posts}.post_content) {$direction}";
}
add_filter( 'posts_orderby', 'wacm_posts_orderby_word_count', 10, 2 );

/* ------------------------------------------------------------------ */
/* Hook everything up for each managed post type                       */
/* ------------------------------------------------------------------ */

function wacm_register_hooks() {
    foreach ( wacm_managed_post_types() as $type ) {
        add_filter( "manage_{$type}_posts_columns", 'wacm_add_columns' );
        add_action( "manage_{$type}_posts_custom_column", 'wacm_fill_column', 10, 2 );
        add_filter( "manage_edit-{$type}_sortable_columns", 'wacm_sortable_columns' );
    }
}
add_action( 'admin_init', 'wacm_register_hooks' );

/* ------------------------------------------------------------------ */
/* Settings page                                                       */
/* ------------------------------------------------------------------ */

function wacm_register_settings() {
    register_setting( 'wacm_settings_group', WACM_OPTION, 'wacm_sanitize_settings' );
}
add_action( 'admin_init', 'wacm_register_settings' );

function wacm_sanitize_settings( $input ) {
    $out = wacm_default_settings();

    $defs = wacm_column_definitions();
    $cols = array();
    foreach ( array_keys( $defs ) as $key ) {
        $cols[ $key ] = ! empty( $input['columns'][ $key ] ) ? 1 : 0;
    }
    $out['columns'] = $cols;

    $types = array();
    if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
        foreach ( $input['post_types'] as $type ) {
            $type = sanitize_key( $type );
            if ( post_type_exists( $type ) ) {
                $types[] = $type;
            }
        }
    }
    $out['post_types'] = ! empty( $types ) ? $types : array( 'post' );

    return $out;
}

function wacm_add_settings_page() {
    add_options_page(
        __( 'Admin Columns', 'wp-admin-columns-manager' ),
        __( 'Admin Columns', 'wp-admin-columns-manager' ),
        'manage_options',
        'wp-admin-columns-manager',
        'wacm_render_settings_page'
    );
}
add_action( 'admin_menu', 'wacm_add_settings_page' );

function wacm_render_settings_page() {
    $settings   = wacm_get_settings();
    $defs       = wacm_column_definitions();
    $post_types = get_post_types( array( 'public' => true ), 'objects' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Admin Columns Manager', 'wp-admin-columns-manager' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wacm_settings_group' ); ?>
            <h2><?php esc_html_e( 'Columns', 'wp-admin-columns-manager' ); ?></h2>
            <p class="description"><?php esc_html_e( 'ID, Words, Last Modified and Comments are sortable — click the column header in the list. The image thumbnail is display-only.', 'wp-admin-columns-manager' ); ?></p>
            <table class="form-table">
                <?php foreach ( $defs as $key => $label ) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $label ); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( WACM_OPTION ); ?>[columns][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings['columns'][ $key ] ) ); ?>> <?php esc_html_e( 'Show', 'wp-admin-columns-manager' ); ?></label></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <h2><?php esc_html_e( 'Post types', 'wp-admin-columns-manager' ); ?></h2>
            <table class="form-table">
                <?php foreach ( $post_types as $type ) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $type->labels->singular_name ); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr( WACM_OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, (array) $settings['post_types'], true ) ); ?>> <?php esc_html_e( 'Show columns', 'wp-admin-columns-manager' ); ?></label></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
