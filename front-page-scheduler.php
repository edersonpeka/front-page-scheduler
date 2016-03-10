<?php
/*
Plugin Name: Front Page Scheduler
Plugin URI: http://ederson.peka.nom.br
Description: Front Page Scheduler plugin let you choose an alternate static front page to be shown during a specific daily period.
Version: 0.1.2
Author: Ederson Peka
Author URI: http://ederson.peka.nom.br
Text Domain: front-page-scheduler
*/

if ( !class_exists( 'front_page_scheduler' ) ) :

class front_page_scheduler {

    // Init
    function init() {
        // Internationalization
        load_plugin_textdomain( 'front-page-scheduler', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

        // Hooking into admin's screens
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
        // Overriding 'show_on_front' option: always 'page' in our specified period
        add_filter( 'option_show_on_front', array( __CLASS__, 'override_option_show_on_front' ) );
        // Overriding 'page_on_front' option: always specified page in our specified period
        add_filter( 'option_page_on_front', array( __CLASS__, 'override_option_page_on_front' ) );

    }

    // Filtering the "what to show on your site's front page" option:
    //   if it's set to show the last posts, and now it's time to show
    //   the alternate front page, we override the option, telling
    //   WordPress to show the specified page.
    function override_option_show_on_front( $what ){

        // Is it set to show the latest posts? And
        if ( 'posts' == $what ) :
            // Is it time to show the alternate front page?
            //   So let's override the option with our specified page.
            $override = call_user_func( array( __CLASS__, 'override_option_page_on_front' ), 0 );
            if ( $override ) $what = 'page';
        endif;

        return $what;

    }

    // Filtering the "which page to show on your site's front" option:
    //   if now it's time to show the alternate front page, we override
    //   the option, telling WordPress to show our alternate page.
    function override_option_page_on_front( $frontpage ){

        // Let's not mess with the settings screen...
        if ( !is_admin() ) {

            // saved options
            $options = get_option( 'front_page_scheduler_options' );
            // alternate page
            $ps_page = intval( '0' . $options[ 'front_page_scheduler_page' ] );
            // validation method
            $func_valid_time = array( __CLASS__, 'valid_time' );
            // get time to start
            $ps_start = call_user_func( $func_valid_time, $options[ 'front_page_scheduler_start' ] );
            // get time to stop
            $ps_stop = call_user_func( $func_valid_time, $options[ 'front_page_scheduler_stop' ] );
            // get weekday to stop
            $op_weekday = $options[ 'front_page_scheduler_weekday' ];

            // if alternate page exists
            if ( $ps_page && get_page( $ps_page ) ) {

                // clean the numbers
                $ps_start = intval( str_replace( ':', '', $ps_start ) );
                $ps_stop = intval( str_replace( ':', '', $ps_stop ) );
                if ( !is_array( $op_weekday ) ) $op_weekday = explode( ',', $op_weekday );
                $ps_weekday = array_filter( $op_weekday, 'is_numeric' );
                if ( !$ps_weekday ) $ps_weekday = array( 0 );
                // set timezone
                if ( $tz = get_option( 'timezone_string' ) ) date_default_timezone_set( $tz );
                // get the time
                $tnow = intval( date( 'Hi' ) );
                // get the week day (today)
                $twday = intval( date( 'w' ) ) + 1;
                // get the week day (yesterday)
                $twyes = ( ( $twday + 6 ) % 8 ) + 1;

                // if our chosen period crosses the midnight, and we're
                //   in it, let's return the alternate page id
                if ( $ps_start > $ps_stop ) {
                    $showtoday = in_array( 0, $ps_weekday ) || in_array( $twday, $ps_weekday );
                    $showyesterday = in_array( 0, $ps_weekday ) || in_array( $twyes, $ps_weekday );
                    if ( ( ( $tnow >= $ps_start ) && $showtoday ) || ( ( $tnow <= $ps_stop ) && $showyesterday ) )
                        $frontpage = $ps_page;
                }

                // if it doesn't cross the midnight, and we're in it,
                //   let's return the alternate page id too
                if ( $ps_stop > $ps_start && $tnow >= $ps_start && $tnow <= $ps_stop )
                    if ( in_array( 0, $ps_weekday ) || in_array( $twday, $ps_weekday ) )
                        $frontpage = $ps_page;

            }

        }
        return $frontpage;
    }

    function admin_init(){

        // Creating a "new section" on "Options > Reading" screen
        add_settings_section( 'front_page_scheduler_settings', __( 'Alternate Front Page Scheduler', 'front-page-scheduler' ), array( __CLASS__, 'text' ), 'reading' );
        // Creating a new "options group" attached to "Options > Reading"
        //   screen. WordPress will automatically save them, after
        //   sanitizing their value through our callback function
        register_setting( 'reading', 'front_page_scheduler_options', array( __CLASS__, 'options_sanitize' ) );
        // Adding fields to our "options group"
        add_settings_field( 'front_page_scheduler_page', __( 'Alternate Front Page', 'front-page-scheduler' ), array( __CLASS__, 'page' ), 'reading', 'front_page_scheduler_settings', array( 'label_for' => 'front_page_scheduler_page' ) );
        add_settings_field( 'front_page_scheduler_start', __( 'Start at', 'front-page-scheduler' ), array( __CLASS__, 'start' ), 'reading', 'front_page_scheduler_settings', array( 'label_for' => 'front_page_scheduler_start' ) );
        add_settings_field( 'front_page_scheduler_weekday', __( 'Week Days', 'front-page-scheduler' ), array( __CLASS__, 'weekday' ), 'reading', 'front_page_scheduler_settings' );
        add_settings_field( 'front_page_scheduler_stop', __( 'Stop at', 'front-page-scheduler' ), array( __CLASS__, 'stop' ), 'reading', 'front_page_scheduler_settings', array( 'label_for' => 'front_page_scheduler_stop' ) );

        // Create "settings" link for this plugin on plugins list
        add_filter( 'plugin_action_links', array( __CLASS__, 'settings_link' ), 10, 2 );
        // Inject some javascript
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
    }

    // Description of our "new section"
    function text() {
        echo '<p>' . __( 'You can choose an alternate static front page to be shown during a specific daily period.', 'front-page-scheduler' ) . '</p>';
        echo '<p class="description">' . sprintf( __( '(Using timezone defined in <a href="%s">general settings.</a>)', 'front-page-scheduler' ), admin_url( 'options-general.php' ) ) . '</p>';
    }

    // Alternate Page field's markup
    function page() {
        $options = get_option( 'front_page_scheduler_options' );
        ?>
        <?php printf( wp_dropdown_pages( array( 'id' => 'front_page_scheduler_page', 'name' => 'front_page_scheduler_options[front_page_scheduler_page]', 'echo' => 0, 'show_option_none' => __( '&mdash; None &mdash;', 'front-page-scheduler' ), 'option_none_value' => '0', 'selected' => $options[ 'front_page_scheduler_page' ] ) ) ); ?>
        <?php
    }
    // Start At field's markup
    function start() {
        $options = get_option( 'front_page_scheduler_options' );
        echo '<input type="text" id="front_page_scheduler_start" name="front_page_scheduler_options[front_page_scheduler_start]" value="' . $options[ 'front_page_scheduler_start' ] . '" maxlength="5" size="5" /> <p class="description">' . __( 'Format: <code>hh:mm</code>', 'front-page-scheduler' ) . '</p>';
    }
    // Stop At field's markup
    function stop() {
        $options = get_option( 'front_page_scheduler_options' );
        echo '<input type="text" id="front_page_scheduler_stop" name="front_page_scheduler_options[front_page_scheduler_stop]" value="' . $options[ 'front_page_scheduler_stop' ] . '" maxlength="5" size="5" /> <p class="description">' . __( 'Format: <code>hh:mm</code>', 'front-page-scheduler' ) . '</p>';
    }
    // Week Day field's markup
    function weekday() {
        $ws = array( '<strong>Everyday</strong>', 'Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays' );
        $options = get_option( 'front_page_scheduler_options' );
        $days = $options[ 'front_page_scheduler_weekday' ];
        if ( !is_array( $days ) ) $days = array( 0 );
        $wid = 0;
        foreach ( $ws as $w ) :
            echo '<label for="front_page_scheduler_weekday_' . $wid . '"><input type="checkbox" id="front_page_scheduler_weekday_' . $wid . '" value="' . $wid . '" name="front_page_scheduler_options[front_page_scheduler_weekday][]" ' . ( ( in_array( $wid, $days ) || in_array( 0, $days ) ) ? ' checked="checked"' : '' ) . ' /> ' . __( $w, 'front-page-scheduler' ) . '</label><br />';
            $wid++;
        endforeach;
    }

    // Sanitize our options
    function options_sanitize( $input ) {

        // saved options
        $options = get_option( 'front_page_scheduler_options' );
        // submitted options
        $ps_page = intval( '0' . $input[ 'front_page_scheduler_page' ] );
        // validation function
        $func_valid_time = array( __CLASS__, 'valid_time' );
        // get time to start
        $ps_start = call_user_func( $func_valid_time, $input[ 'front_page_scheduler_start' ] );
        // get time to stop
        $ps_stop = call_user_func( $func_valid_time, $input[ 'front_page_scheduler_stop' ] );
        // get week day
        $ps_weekday = array_filter( $input[ 'front_page_scheduler_weekday' ], 'is_numeric' );
        if ( in_array( 0, $ps_weekday ) ) $ps_weekday = array( 0 );

        // if alternate page was set
        if ( $ps_page ) {

            // save the options
            $options[ 'front_page_scheduler_page' ] = $ps_page;
            $options[ 'front_page_scheduler_start' ] = $ps_start;
            $options[ 'front_page_scheduler_stop' ] = $ps_stop;
            $options[ 'front_page_scheduler_weekday' ] = $ps_weekday;

        // else
        } else {

            // clean the options
            $options[ 'front_page_scheduler_page' ] = '';
            $options[ 'front_page_scheduler_start' ] = '';
            $options[ 'front_page_scheduler_stop' ] = '';
            $options[ 'front_page_scheduler_weekday' ] = false;

        }
        return $options;
    }

    // Validate time input
    function valid_time( $t ) {

        // remove invalid chars
        $t = preg_replace( '/[^0-9:]/im', '', $t );
        // breaking
        $at = explode( ':', $t );
        $t = '';
        if ( count( $at ) <= 2 ) {
            // getting each part
            $hora = array_shift( $at );
            $minuto = count( $at ) ? array_shift( $at ) : 0;
            // converting
            $hora = intval( '0' . $hora );
            $minuto = intval( '0' . $minuto );
            // summing
            $minutos = ( $hora * 60 ) + $minuto;
            // checking validity
            if ( $minutos >= 0 && $minutos < ( 23 * 60 ) + 59 )
                $t = substr( '0' . $hora, -2 ) . ':' . substr( '0' . $minuto, -2 );
        }
        return $t;
    }

    // Add Settings link to plugins - code from GD Star Ratings
    // (as seen in http://www.whypad.com/posts/wordpress-add-settings-link-to-plugins-page/785/ )
    function settings_link( $links, $file ) {
        $this_plugin = plugin_basename(__FILE__);
        if ( $file == $this_plugin ) {
            $settings_link = '<a href="' . admin_url( 'options-reading.php' ) . '">' . __( 'Settings', 'front-page-scheduler' ) . '</a>';
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    function admin_enqueue_scripts( $suff ) {
        if ( 'options-reading.php' == $suff ) {
            ?>
            <script type="text/javascript">
            var front_page_scheduler_winps = false;
            function front_page_scheduler_winps_change() {
                var winps = front_page_scheduler_winps;
                if ( jQuery( this ).is( '#front_page_scheduler_weekday_0' ) ) {
                    winps.attr( 'checked', jQuery( this ).attr( 'checked' ) ? 'checked' : false );
                } else {
                    var wall = true;
                    winps.each( function () {
                        if ( !jQuery( this ).is( '#front_page_scheduler_weekday_0' ) ) {
                            wall = wall && jQuery( this ).attr( 'checked' );
                        }
                        return wall;
                    } );
                    console.log( wall ? 'marca' : 'desmarca' );
                    jQuery( '#front_page_scheduler_weekday_0' ).attr( 'checked', wall ? 'checked' : false );
                }
            }
            window.onload = function () {
                front_page_scheduler_winps = jQuery( 'input[name="front_page_scheduler_options[front_page_scheduler_weekday][]"]' ).change( front_page_scheduler_winps_change );
            };
            </script>
            <?php
        }
    }

}

// Initialize
add_action( 'init', array( 'front_page_scheduler', 'init' ) );

endif;

?>
