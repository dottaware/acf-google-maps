<?php

/**
 * Plugin Name: Advanced Custom Fields: Google Maps
 * Plugin URI: https://github.com/dottaware/acf-google-maps/
 * Description: Wordpress plugin that shows a Google Maps on the frontend.
 * Author: Stefano Dotta
 * Text Domain: acf-google-maps
 * Domain Path: /languages
 * Version: 1.3
**/

if ( ! defined('ABSPATH') ) {
    die('Please do not load this file directly.');
}


class ACF_Google_Maps_Widget extends WP_Widget {

    private $defaults;

    private $geo_metadata;

    private $googlemaps_js_args;

    public function __construct() {

        $widget_title = __('ACF Google Maps');

        $widget_ops = array('classname' => 'acf-google-maps', 
                            'description' => __('Show a Google Maps on the frontend.'));

        $control_ops = array(
                'id_base'  => 'acf-google-maps',
                'width'    => 400, 
                'height'   => 350,
        );
        
        parent::__construct('acf-google-maps', $widget_title, $widget_ops, $control_ops);
      
        $this->defaults = array(
                'title' => 'Google Maps',
        );

        $this->googlemaps_js_args = array(
                'key' => get_option('options_google_maps_api'),
                'ver' => 'weekly',
        );

        $this->create_option_page();

    }
    
    
    // Outputs the content for the widget instance.
    public function widget( $args, $instance ) {
      
        // Only works with single posts.
        if ( ! is_single() ) {
            return;
        }
          
        // Bail out if empty Google Maps API key.
        if ( empty( $this->googlemaps_js_args['key'] ) ) {
            return;
        }

        // Get post geo metadata.
        $this->geo_metadata = $this->get_post_geo_metadata();

        // Bail out if no geo metadata found.
        if ( empty( $this->geo_metadata ) ) {
            return;
        }

        // Load the required scripts.
        $this->enqueue_frontend_scripts();

        // Merge with defaults.
        $instance = wp_parse_args( (array) $instance, $this->defaults );

        echo $args['before_widget'];

        $title = $instance['title'] ? $instance['title'] : $this->defaults['title'];

        echo $args['before_title'] . apply_filters( 'widget_title', $title, $instance, $this->id_base ) . $args['after_title'];

        // Show the map on the frontend.
        $this->output_google_map();

        echo $args['after_widget'];

    }


    /**
     *
     *
     */
    private function output_google_map() {

        // Print out the google map.
        ?>
        <div class="acf-map">
            <div class="marker" data-lat="<?php echo $this->geo_metadata['lat']; ?>" data-lng="<?php echo $this->geo_metadata['lng']; ?>" data-title="<?php echo $this->geo_metadata['location']; ?>">
                <div class="content"><?php echo $this->geo_metadata['description']; ?></div>
            </div>
        </div>
        <?php

    }
  
    /**
     * Handles updating settings for the current widget instance.
     *
     */
    public function update( $new_instance, $old_instance ) {
      
        $instance = $old_instance;
        
        $instance['title'] = sanitize_text_field( $new_instance['title'] );
        $instance['height'] = (int) $new_instance['height'];

        return $instance;
    }

    /**
     * Outputs the widget settings form.
     *
     */
    public function form( $instance ) {

        // Merge with defaults.
        $instance = wp_parse_args( (array) $instance, $this->defaults );

        $title = sanitize_text_field( $instance['title'] );

        $height = isset( $instance['height'] ) ? (int) $instance['height'] : 300;

        ?>

        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php _e( 'Widget height:'); ?></label>
            <input id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="number" value="<?php echo (int) $height; ?>" min="100" max="900" />
        </p>

        <?php
    }


    /**
     * Create the Google Maps js url.
     *
     */
    private function get_googlemaps_script_url() {

        $script_url = esc_url_raw( add_query_arg( $this->googlemaps_js_args, 'https://maps.googleapis.com/maps/api/js' ) );
      
        return $script_url;
    }


    // Outputs the Google Maps scripts on the frontend.
    private function enqueue_frontend_scripts() {

        wp_enqueue_script( 'acf-google-maps-api', $this->get_googlemaps_script_url(), array(), null, true );
        wp_enqueue_script( 'acf-google-maps-init', plugin_dir_url( __FILE__ ) . '/js/acf-google-maps.js', array('acf-google-maps-api', 'jquery'), null, true );

    }

    /**
     *
     *
     */
    private function get_post_geo_metadata() {
        global $post;
        $post_id = $post->ID;

        // Get geo metadata from ACF.
        if ( metadata_exists( 'post', $post_id, 'geo_coordinates' ) ) {

            $geo_coordinates = get_post_meta( $post_id, 'geo_coordinates', true );
        
            // Unserialize the ACF field.
            $geo_metadata = maybe_unserialize( $geo_coordinates );
        
            // Get location and description.
            $geo_metadata['location'] = get_post_meta( $post_id, 'geo_location', true );
            $geo_metadata['description'] = get_post_meta( $post_id, 'geo_description', true );
        
            // If location is empty, try using the address value from ACF.
            $geo_metadata['location'] = $geo_metadata['location'] ? $geo_metadata['location'] : $geo_metadata['address'];
        
            // As last resort, use the post title.
            if ( empty( $geo_metadata['location'] ) ) {
                $geo_metadata['location'] = $post->post_title;
            }

            // If description is empty, use the location.
            $geo_metadata['description'] = $geo_metadata['description'] ? $geo_metadata['description'] : $geo_metadata['location'];

            // Add line breaks to the description field.
            $geo_metadata['description'] = wpautop( $geo_metadata['description'] );

            // Only return metadata if both latitude and longitude exist.
            if ( $geo_metadata['lat'] && $geo_metadata['lng'] ) {
                return $geo_metadata;
            }
        }

        // Get geo metadata from old plugin WP Geo.
        if ( metadata_exists( 'post', $post_id, '_wp_geo_latitude' ) ) {

            $geo_metadata = array(
                'location'  => get_post_meta( $post_id, '_wp_geo_title', true ),
                'lat'   => get_post_meta( $post_id, '_wp_geo_latitude', true ),
                'lng'  => get_post_meta( $post_id, '_wp_geo_longitude', true ),
            );

            // If location is empty, use the post title.
            $geo_metadata['location'] = $geo_metadata['location'] ? $geo_metadata['location'] : $post->post_title;

            // If description is empty, use the location.
            $geo_metadata['description'] = $geo_metadata['description'] ? $geo_metadata['description'] : $geo_metadata['location'];

            // Add line breaks to the description field.
            $geo_metadata['description'] = wpautop( $geo_metadata['description'] );

            // Only return metadata if both latitude and longitude exist.
            if ( $geo_metadata['lat'] && $geo_metadata['lng'] ) {
                return $geo_metadata;
            }
        }

        // if nothing has been found, return an empty value.
        return;

    }

    /**
     *
     *
     */
    private function create_option_page() {

        if( function_exists('acf_add_options_sub_page') ) {
            acf_add_options_sub_page( array(
                'page_title'    => 'ACF Google Maps',
                'menu_title'    => 'ACF Google Maps',
                'menu_slug'     => 'acf-google-maps',
                'parent_slug'   => 'options-general.php',
                'capability'    => 'manage_options',
            ) );
        }

    }


} // end Widget class


// Register and load the widget
function acf_google_maps_widget_load() {
    register_widget( 'ACF_Google_Maps_Widget' );
}
add_action( 'widgets_init', 'acf_google_maps_widget_load' );
