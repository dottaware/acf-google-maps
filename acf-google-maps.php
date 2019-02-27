<?php

/**
 * Plugin Name: Advanced Custom Fields: Google Maps
 * Plugin URI: https://github.com/dottaware/acf-google-maps/
 * Description: Wordpress plugin that shows a Google Maps on the frontend.
 * Author: Stefano Dotta
 * Text Domain: acf-google-maps
 * Domain Path: /languages
 * Version: 1.0
**/

if ( ! defined('ABSPATH') ) {
    die('Please do not load this file directly.');
}


class ACF_Google_Maps_Widget extends WP_Widget {

    private $defaults;

    private $location = ['address' => '', 'lat' => '', 'lng' => '',];

    private $googlemaps_js_args;

    private $post_id;


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
                'title'           => 'Google Maps',
                'google_maps_api' => get_option('options_google_maps_api'),
        );

        $this->location = ['geo_location' => 'Adresse manquante !'];

        $this->create_option_page();

    }
    
    
    // Outputs the content for the widget instance.	 
    public function widget( $args, $instance ) {
      
        // Only works with single posts.
        if ( ! is_single() ) {
            return;
        }
          
        // Merge with defaults.
        $instance = wp_parse_args( (array) $instance, $this->defaults );
      
        // $this->defaults['google_maps_api'] = get_option('options_google_maps_api');
        // Bail out if empty Google Maps API key.
        if ( empty( $this->defaults['google_maps_api'] ) ) {
            return;
        }
      
        // save Google Maps API in the class variable.
        // $this->defaults['google_maps_api'] = $instance['google_maps_api'];
      
        // store the post_ID in a class variable.
        $this->post_id =  get_the_ID();

        // Get post location coordinates.
        $this->location = $this->get_post_location();
      
        // Bail out if empty (no coordinates).
        if ( empty( $this->location ) ) {
            return;
        }
          
        // Load the required scripts.
        $this->enqueue_frontend_scripts();

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
            <div class="marker" data-lat="<?php echo $this->location['lat']; ?>" data-lng="<?php echo $this->location['lng']; ?>">
                <h5 class="address"><?php echo $this->location['geo_location']; ?></h5>
                <p class="content"><?php echo $this->location['geo_description']; ?></p>
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
        $instance['google_maps_api'] = $new_instance['google_maps_api'];
        
        $instance['height'] = (int) $new_instance['height'];
        if ( $instance['count'] < 100 || 100 < $instance['count'] ) {
            $instance['count'] = 100;
        }

        return $instance;
    }

    /**
     * Outputs the widget settings form.
     *
     */
    public function form( $instance ) {

        // Merge with defaults.
        $instance = wp_parse_args( (array) $instance, $this->defaults );

        $google_maps_api = $instance['google_maps_api'];

        $title = sanitize_text_field( $instance['title'] );

        $height = isset( $instance['height'] ) ? (int) $instance['height'] : 100;

        ?>

        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('google_maps_api'); ?>"><?php _e('Google Maps API:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('google_maps_api'); ?>" name="<?php echo $this->get_field_name('google_maps_api'); ?>" type="text" value="<?php echo esc_attr($google_maps_api); ?>" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'height' ); ?>"><?php esc_html_e( 'Maximum number of posts to show (no more than 10):', 'jetpack' ); ?></label>
            <input id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="number" value="<?php echo (int) $height; ?>" min="100" max="900" />
        </p>

        <?php
    }


    /**
     * Create the Google Maps js url.
     *
     */
    private function get_googlemaps_script_url() {

        $this->googlemaps_js_args = array(
            'key'       => $this->defaults['google_maps_api'],
            'language'  => 'fr',
            'ver'		=> 'weekly',
        );

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
    private function get_post_location() {

        // Get location data.
        $location = get_post_meta( $this->post_id, 'geo_coordinates', true );

        // No term, no glory...
        if ( ! $location || empty( $location ) ) {
            return;
        }

        // Unserialize the ACF field.
        $location = maybe_unserialize( $location );

        // Get location and description.
        $location['geo_location']  = get_post_meta( $this->post_id, 'geo_location', true );
        $location['geo_description']  = get_post_meta( $this->post_id, 'geo_description', true );

        // If the address is empty, use geo_location.
        $location['address'] = $location['address'] ? $location['address'] : $location['geo_location'];

        return $location;

    }

    private function create_option_page() {

        if( function_exists('acf_add_options_sub_page') ) {
            acf_add_options_sub_page( array(
                'page_title'    => 'ACF Google Maps',
                'menu_title'    => 'ACF Google Maps',
                'menu_slug'     => 'acf-google-maps',
                // 'position'   => 10.3,
                // 'parent_slug'     'options-general.php',
        ) );
    }

    }


} // end Widget class


// Register and load the widget
function acf_google_maps_widget_load() {
    register_widget( 'ACF_Google_Maps_Widget' );
}
add_action( 'widgets_init', 'acf_google_maps_widget_load' );
