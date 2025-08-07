<?php
/*
Plugin Name: Front Page Scheduler
Plugin URI: https://ederson.ferreira.tec.br
Description: Front Page Scheduler plugin let you choose an alternate static front page to be shown during a specific daily period.
Version: 0.1.91
Author: Ederson Peka
Author URI: https://profiles.wordpress.org/edersonpeka/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: front-page-scheduler
*/

if ( !class_exists( 'front_page_scheduler' ) ) :

class front_page_scheduler {

    // Init
    public static function init() {
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
    public static function override_option_show_on_front( $what ) {

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
    public static function override_option_page_on_front( $frontpage ) {

        // Thou shalt not mess with the settings screen...
        if ( !is_admin() ) {

            // Saving original to detect changes and break the loop
            $original_frontpage = $frontpage;

            // saved options (rules)
            $arr_options = (array) get_option( 'front_page_scheduler_options' );
            // compatibility with legacy options format (which used to allow only one rule)
            if ( !array_key_exists( 'front_page_scheduler_json', $arr_options ) ) :
                // if there's only one rule saved (legacy), we turn it into an "array of one rule"
                $arr_options = array( $arr_options );
            // current format
            else :
                // down one level
                $arr_options = $arr_options[ 'front_page_scheduler_json' ];
            endif;
            // iterate over each saved rule
            if ( $arr_options ) foreach ( $arr_options as $options ) :
                // get alternate page
                $ps_page = intval( '0' . $options[ 'front_page_scheduler_page' ] );
                // time validation method
                $func_valid_time = array( __CLASS__, 'valid_time' );
                // get and validate time to start
                $ps_start = call_user_func( $func_valid_time, $options[ 'front_page_scheduler_start' ] );
                // get and validate time to stop
                $ps_stop = call_user_func( $func_valid_time, $options[ 'front_page_scheduler_stop' ] );
                // get week day to stop
                $op_weekday = $options[ 'front_page_scheduler_weekday' ];

                // if alternate page exists
                if ( $ps_page && get_page( $ps_page ) ) {

                    // clean the numbers
                    $ps_start = intval( str_replace( ':', '', $ps_start ) );
                    $ps_stop = intval( str_replace( ':', '', $ps_stop ) );
                    // break string into an array (should be already, but who knows)
                    if ( !is_array( $op_weekday ) ) $op_weekday = explode( ',', $op_weekday );
                    // week day should be numeric
                    $ps_weekday = array_filter( $op_weekday, 'is_numeric' );
                    // if it's not, we assume it's zero
                    if ( !$ps_weekday ) $ps_weekday = array( 0 );
                    // set current timezone
                    if ( $tz = get_option( 'timezone_string' ) ) date_default_timezone_set( $tz );
                    // get the current time
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
                // if we've got an alternate frontpage already, we can break the loop
                if ( $original_frontpage != $frontpage ) break;
            endforeach;
        }
        return $frontpage;
    }

    public static function admin_init() {

        // Creating a "new section" on "Options > Reading" screen
        add_settings_section( 'front_page_scheduler_settings', __( 'Alternate Front Page Scheduler', 'front-page-scheduler' ), array( __CLASS__, 'text' ), 'reading' );
        // Creating a new "options group" attached to "Options > Reading"
        //   screen. WordPress will automatically save them, after
        //   sanitizing their value through our callback function
        register_setting( 'reading', 'front_page_scheduler_options', array( __CLASS__, 'options_sanitize' ) );
        // Adding hidden field (it will be handled by JavaScript)
        add_settings_field( 'front_page_scheduler_json', __( 'Scheduling Rules', 'front-page-scheduler' ), array( __CLASS__, 'json_field' ), 'reading', 'front_page_scheduler_settings' );

        // Create "settings" link for this plugin on plugins list
        add_filter( 'plugin_action_links', array( __CLASS__, 'settings_link' ), 10, 2 );
        // Inject some javascript
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
    }

    // Description of our "new section"
    public static function text() {
        echo '<p>' . __( 'You can choose an alternate static front page to be shown during a specific daily period.', 'front-page-scheduler' ) . '</p>';
        echo '<p class="description">' . sprintf( __( '(Using timezone defined in <a href="%s">general settings.</a>)', 'front-page-scheduler' ), admin_url( 'options-general.php' ) ) . '</p>';
    }

    // JSON rules markup
    public static function json_field() {
        // get saved options
        $options = get_option( 'front_page_scheduler_options' );
        // nothing saved?
        if ( !$options ) :
            // preparing an "empty" array (under our expected structure)
            $options = array( 'front_page_scheduler_json' => '' );
        // something saved, but not the "JSON" field? (legacy)
        elseif ( !array_key_exists( 'front_page_scheduler_json', $options ) ) :
            // preparing an "JSON" array using old options (under our expected current structure)
            $options[ 'front_page_scheduler_json' ] = array( $options );
        endif;
        // echoing a hidden field containing our serialized array (JavaScript's gonna handle it)
        echo '<fieldset class="--json-container"><input type="hidden" id="front_page_scheduler_json" name="front_page_scheduler_options[front_page_scheduler_json]" value="' . esc_attr( json_encode( $options[ 'front_page_scheduler_json' ] ) ) . '" /></fieldset>';        
    }

    // Sanitize our options
    public static function options_sanitize( $ops ) {
        global $_front_page_scheduler_sanitize_not_first_call;
        // avoiding weird wordpress bug that runs sanitize multiple times when you first save the option
        if ( !isset( $_front_page_scheduler_sanitize_not_first_call ) ) :
            $_front_page_scheduler_sanitize_not_first_call = true;
            // sanitizing options array
            if ( !is_array( $ops ) ) $ops = array();
            // if we do not receive the expected format, we create an "empty" array
            if ( !array_key_exists( 'front_page_scheduler_json', $ops ) ) $ops[ 'front_page_scheduler_json' ] = '[]';
            // initializing rules array
            $rules = array();
            // try to decode received JSON
            try {
                $inputs = json_decode( (string) $ops[ 'front_page_scheduler_json' ], true );
            } catch (Exception $e) {
                // any trouble? Sets an empty array and the show must go on
                $inputs = array();
            }
            // iterating over groups of inputs
            foreach ( $inputs as $input ) :
                // initializing "suboptions" array
                $options = array();
                // submitted page on this group
                $ps_page = intval( '0' . $input[ 'front_page_scheduler_page' ] );
                // validation function
                $func_valid_time = array( __CLASS__, 'valid_time' );
                // get (and validate) time to start on this group
                $ps_start = call_user_func( $func_valid_time, $input[ 'front_page_scheduler_start' ] );
                // get (and validate) time to stop on this group
                $ps_stop = call_user_func( $func_valid_time, $input[ 'front_page_scheduler_stop' ] );
                // get week day on this group
                $ps_weekday = array_filter( $input[ 'front_page_scheduler_weekday' ], 'is_numeric' );
                // if day "0" (everyday) is set, it's the only day needed (cause it means "everyday", right?)
                if ( in_array( 0, $ps_weekday ) ) $ps_weekday = array( 0 );

                // if alternate page was set on this group
                if ( $ps_page ) {

                    // save the options
                    $options[ 'front_page_scheduler_page' ] = $ps_page;
                    $options[ 'front_page_scheduler_start' ] = $ps_start;
                    $options[ 'front_page_scheduler_stop' ] = $ps_stop;
                    $options[ 'front_page_scheduler_weekday' ] = $ps_weekday;
                    // append to rules array
                    $rules[] = $options;

                }
            endforeach;
            // merge rules
            $ops[ 'front_page_scheduler_json' ] = $rules;
        endif;
        // return sanitized options
        return $ops;
    }

    // Validate time input
    public static function valid_time( $t ) {

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
    public static function settings_link( $links, $file ) {
        $this_plugin = plugin_basename(__FILE__);
        if ( $file == $this_plugin ) {
            $settings_link = '<a href="' . admin_url( 'options-reading.php' ) . '">' . __( 'Settings', 'front-page-scheduler' ) . '</a>';
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    public static function admin_enqueue_scripts( $suff ) {
        // Thou shalt mess only with the "reading" settings screen...
        if ( 'options-reading.php' == $suff ) {
            // enqueue javascript file
            wp_enqueue_script( 'front-page-scheduler-js', plugin_dir_url( __FILE__ ) . '/front-page-scheduler.js', array( 'jquery' ), date( 'YmdHis', filemtime( dirname(__FILE__) . '/front-page-scheduler.js' ) ), true );
            // get list of pages
            $get_pages = get_posts( array( 'numberposts' => -1, 'post_type' => 'page', 'orderby' => 'post_title', 'order' => 'ASC' ) );
            // initialize empty array
            $pages = array();
            // populate array with page IDs and titles
            foreach ( $get_pages as $get_page ) $pages[] = array( 'id' => $get_page->ID, 'title' => get_the_title( $get_page->ID ) );
            // localized strings
            wp_localize_script( 'front-page-scheduler-js', '_fps_strings', array(
                'add-rule' => __( 'Add Rule', 'front-page-scheduler' ),
                'remove-rule' => __( 'Remove Rule', 'front-page-scheduler' ),
                'remove-rule-confirm' => __( 'This action can not be undone! Are you sure?', 'front-page-scheduler' ),
                'time-format' => __( 'Format: <code>hh:mm</code>', 'front-page-scheduler' ),
                'alternate-front-page' => __( 'Alternate Front Page', 'front-page-scheduler' ),
                'none' => __( '&mdash; None &mdash;', 'front-page-scheduler' ),
                'pages' => $pages,
                'start-at' => __( 'Start at', 'front-page-scheduler' ),
                'stop-at' => __( 'Stop at', 'front-page-scheduler' ),
                'week-days' => __( 'Week Days', 'front-page-scheduler' ),
                'week-days-names' => array(
                    __( '<strong>Everyday</strong>', 'front-page-scheduler' ),
                    __( 'Sundays', 'front-page-scheduler' ),
                    __( 'Mondays', 'front-page-scheduler' ),
                    __( 'Tuesdays', 'front-page-scheduler' ),
                    __( 'Wednesdays', 'front-page-scheduler' ),
                    __( 'Thursdays', 'front-page-scheduler' ),
                    __( 'Fridays', 'front-page-scheduler' ),
                    __( 'Saturdays', 'front-page-scheduler' ),
                ),
            ) );
            // a few CSS rules
            ?>
            <style rel="stylesheet" type="text/css">
            .front_page_scheduler_weekday_table { border-collapse: collapse; }
            .front_page_scheduler_weekday_table tbody td { border: 1px solid #DDD; padding: .3em 1em 0; text-align: center; }
            .front_page_scheduler_weekday_table tbody td input { margin: .2em 0 .5em; }
            .front_page_scheduler_rule_table { border: 1px solid #AAA; margin: 1em 0; padding: 0 1em; background-color: #FFF; }
            .front_page_scheduler_rule_table tr th { width: auto; }
            </style>
            <?php
        }
    }

}

// Initialize
add_action( 'init', array( 'front_page_scheduler', 'init' ) );

endif;

?>
