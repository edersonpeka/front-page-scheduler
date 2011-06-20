<?php
/*
Plugin Name: Front Page Scheduler
Plugin URI: http://ederson.peka.nom.br
Description: Front Page Scheduler plugin let you choose an alternate static front page to be shown during a specific daily period.
Version: 0.1
Author: Ederson Peka
Author URI: http://ederson.peka.nom.br
*/


// Filtering the "what to show on your site's front page" option:
//   if it's set to show the last posts, and now it's time to show
//   the alternate front page, we override the option, telling
//   WordPress to show the selected page.
add_filter( 'option_show_on_front', 'front_page_scheduler_override_option_show_on_front' );
function front_page_scheduler_override_option_show_on_front( $what ){

    // Is it set to show the latest posts? And is it time to show
    //    the alternate front page? So let's override the option.
    if ( 'posts' == $what && front_page_scheduler_override_option_page_on_front( 0 ) ) $what = 'page';
    
    return $what;
    
}

// Filtering the "which page to show on your site's front" option:
//   if now it's time to show the alternate front page, we override
//   the option, telling WordPress to show our alternate page.
add_filter( 'option_page_on_front', 'front_page_scheduler_override_option_page_on_front' );
function front_page_scheduler_override_option_page_on_front( $frontpage ){

    // Let's not mess with the settings screen...
    if ( !is_admin() ) {
    
        // saved options
        $options = get_option( 'front_page_scheduler_options' );
        // alternate page
        $ps_page = intval( '0' . $options[ 'front_page_scheduler_page' ] );
        // time to start
        $ps_start = front_page_scheduler_valid_time( $options[ 'front_page_scheduler_start' ] );
        // time to stop
        $ps_stop = front_page_scheduler_valid_time( $options[ 'front_page_scheduler_stop' ] );
        
        // if alternate page exists
        if ( $ps_page && get_page( $ps_page ) ) {
        
            // clean the numbers
            $ps_start = intval( str_replace( ':', '', $ps_start ) );
            $ps_stop = intval( str_replace( ':', '', $ps_stop ) );
            // set timezone
            if ( $tz = get_option( 'timezone_string' ) ) date_default_timezone_set( $tz );
            // get the time
            $agora = intval( date( 'Hi' ) );
            
            // if our chosen period crosses the midnight, and we're
            //   in it, let's return the alternate page id
            if ( $ps_start > $ps_stop && ( $agora >= $ps_start || $agora <= $ps_stop ) )
                $frontpage = $ps_page;
                
            // if it doesn't cross the midnight, and we're in it,
            //   let's return the alternate page id too
            if ( $ps_stop > $ps_start && $agora >= $ps_start && $agora <= $ps_stop )
                $frontpage = $ps_page;

        }

    }
    return $frontpage;
}

// Hooking into admin's screens
add_action( 'admin_init', 'front_page_scheduler_init' );
function front_page_scheduler_init(){

    // Creating a "new section" on "Options > Reading" screen
    add_settings_section( 'front_page_scheduler_settings', __( 'Alternate Front Page Scheduler' ), 'front_page_scheduler_text', 'reading' );
    // Creating a new "options group" attached to "Options > Reading"
    //   screen. WordPress will automatically save them, after
    //   sanitizing their value through our callback function
    register_setting( 'reading', 'front_page_scheduler_options', 'front_page_scheduler_options_sanitize' );
    // Adding fields to our "options group"
    add_settings_field( 'front_page_scheduler_page', __( 'Alternate Front Page' ), 'front_page_scheduler_page', 'reading', 'front_page_scheduler_settings' );
    add_settings_field( 'front_page_scheduler_start', __( 'Start at' ), 'front_page_scheduler_start', 'reading', 'front_page_scheduler_settings' );
    add_settings_field( 'front_page_scheduler_stop', __( 'Stop at' ), 'front_page_scheduler_stop', 'reading', 'front_page_scheduler_settings' );

}

// Description of our "new section"
function front_page_scheduler_text() {
    echo '<p>' . __( 'You can choose an alternate static front page to be shown during a specific daily period.' ) . '</p>';
}

// Alternate Page field's markup
function front_page_scheduler_page() {
    $options = get_option( 'front_page_scheduler_options' );
    ?>
    <?php printf( wp_dropdown_pages( array( 'id' => 'front_page_scheduler_page', 'name' => 'front_page_scheduler_options[front_page_scheduler_page]', 'echo' => 0, 'show_option_none' => __( '&mdash; None &mdash;' ), 'option_none_value' => '0', 'selected' => $options[ 'front_page_scheduler_page' ] ) ) ); ?>
    <?php
}
// Start At field's markup
function front_page_scheduler_start() {
    $options = get_option( 'front_page_scheduler_options' );
    echo '<input type="text" name="front_page_scheduler_options[front_page_scheduler_start]" value="' . $options[ 'front_page_scheduler_start' ] . '" maxlength="5" size="5" /> <small>' . __( '(hh:mm)' ) . '</small>';
}
// Stop At field's markup
function front_page_scheduler_stop() {
    $options = get_option( 'front_page_scheduler_options' );
    echo '<input type="text" name="front_page_scheduler_options[front_page_scheduler_stop]" value="' . $options[ 'front_page_scheduler_stop' ] . '" maxlength="5" size="5" /> <small>' . __( '(hh:mm)' ) . '</small>';
}

// Sanitize our options
function front_page_scheduler_options_sanitize( $input ) {

    // saved options
    $options = get_option( 'front_page_scheduler_options' );
    // submitted options
    $ps_page = intval( '0' . $input[ 'front_page_scheduler_page' ] );
    $ps_start = front_page_scheduler_valid_time( $input[ 'front_page_scheduler_start' ] );
    $ps_stop = front_page_scheduler_valid_time( $input[ 'front_page_scheduler_stop' ] );
    
    // if alternate page was set
    if ( $ps_page ) {
    
        // save the options
        $options[ 'front_page_scheduler_page' ] = $ps_page;
        $options[ 'front_page_scheduler_start' ] = $ps_start;
        $options[ 'front_page_scheduler_stop' ] = $ps_stop;
    
    // else
    } else {
    
        // clean the options
        $options[ 'front_page_scheduler_page' ] = '';
        $options[ 'front_page_scheduler_start' ] = '';
        $options[ 'front_page_scheduler_stop' ] = '';
    
    }
    return $options;
}

// Validate time input
function front_page_scheduler_valid_time( $t ) {

    // valid chars
    $t = preg_replace( '/[^0-9:]/im', '', $t );
    // breaking
    $at = explode( ':', $t );
    $t = '';
    if ( count( $at ) <= 2 ) {
        // getting each part
        $hora = array_shift( &$at );
        $minuto = count( $at ) ? array_shift( &$at ) : 0;
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

?>
