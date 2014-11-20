<?php
/**
 * Plugin Name: DobsonDev Weather
 * Plugin URI: http://dobsondev.com/portfolio/dobsondev-weather/
 * Description: A plugin for displaying local weather in a widget and shortcode.
 * Version: 1.0
 * Author: Alex Dobson
 * Author URI: http://dobsondev.com/
 * License: GPLv2
 *
 * Copyright 2014  Alex Dobson  (email : alex@vitaleffect.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


/* Enqueue the Style Sheet */
function dobsondev_weather_enqueue_scripts() {
  wp_enqueue_style( 'dobsondev-weather-app', plugins_url( 'dobsondev-weather-app.css' , __FILE__ ) );
}
add_action( 'wp_enqueue_scripts', 'dobsondev_weather_enqueue_scripts' );


/* Create custom plugin settings menu */
function dobsondev_weather_create_menu() {

  // Create new top-level menu
  add_submenu_page( 'options-general.php', 'DobsonDev Weather Settings', 'DobsonDev Weather', 'administrator', __FILE__, 'dobsondev_weather_settings' );

  // Call register settings function
  add_action( 'admin_init', 'dobsondev_weather_settings_register' );
}
add_action( 'admin_menu', 'dobsondev_weather_create_menu' );


/* Register our settings (we only have one) with WordPress */
function dobsondev_weather_settings_register() {
  // Register DobsonDev Weather settings
  register_setting( 'dobsondev_weather_settings_group', 'dobsondev_weather_api_key' );
}


/* The content for the settings page on the backend */
function dobsondev_weather_settings() {
?>
<div class="wrap">
<h2> DobsonDev Weather </h2>
<p>If you need an API Key, please sign up at <a href="http://openweathermap.org/register">Open Weather Map</a>.</p>
<form method="post" action="options.php">
    <?php settings_fields( 'dobsondev_weather_settings_group' ); ?>
    <?php do_settings_sections( 'dobsondev_weather_settings_group' ); ?>
    <table class="form-table">
        <tr valign="top">
          <th scope="row">API Key</th>
          <td><input type="text" name="dobsondev_weather_api_key" size="32" value="<?php echo esc_attr( get_option('dobsondev_weather_api_key') ); ?>" /></td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
</div>
<?php }


/* Add the Widget */
function register_dobsondev_weather_widget() {
  register_widget( 'dobsondev_weather_widget' );
}
add_action( 'widgets_init', 'register_dobsondev_weather_widget' );


/* The Widget Class */
class dobsondev_weather_widget extends WP_Widget {

  /**
   * Add the dobsondev_weather_widget to the WordPress core.
   */
  public function __construct() {
    parent::__construct(
      'dobsondev_weather_widget', // Base ID
      __('DobsonDev Weather Widget', 'text_domain'), // Name
      array( 'description' => __( 'Displays your local Weather in a nice app widget.', 'text_domain' ), ) // Args
    );
  }

  /**
   * Back-end widget form for admin.
   *
   * @param array $instance       Previously saved values from database.
   */
  public function form( $instance ) {
    if ( isset($instance[ 'location' ]) ) {
      $location = $instance[ 'location' ];
    } else {
      $location = __( '', 'bc_widget_title' );
    }
    ?>
    City/Town: <input name="<?php echo $this->get_field_name( 'location' ); ?>" type="text" placeholder="Edmonton, CA" value="<?php echo esc_attr( $location );?>" />
    <?php
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @param array $new_instance   Values just sent to be saved.
   * @param array $old_instance   Previously saved values from database.
   *
   * @return array                Updated safe values to be saved.
   */
  public function update( $new_instance, $old_instance ) {
    $instance = $old_instance;

    $instance['location'] = ( ! empty( $new_instance['location'] ) ) ? strip_tags( $new_instance['location'] ) : '';

    return $instance;
  }

  /**
   * Front-end display of widget.
   *
   * @param array $args           Widget arguments.
   * @param array $instance       Saved values from database.
   */
  public function widget( $args, $instance ) {
    extract( $args );

    $location = empty( $instance['location'] ) ? '' : $instance['location'];
    // Ensure the location has no spaces when we use in for the API call
    $location = str_replace( ' ', '', $location );

    // Get the API key from the settings
    $api_key = get_option( 'dobsondev_weather_api_key' );

    // Get the 3-hour forecast for current weather conditions
    $current_weather_url = "http://api.openweathermap.org/data/2.5/weather?q=" . $location . "&units=metric&APPID=" . $api_key;

    // Make the API call for the current weather
    $current_weather_result = file_get_contents( $current_weather_url );
    // Make the JSON from the API call into an array
    $current_weather_json = json_decode( $current_weather_result );

    // Couldn't find the city/something else went wrong
    if ( $current_weather_json->cod == "404" ) {
      $city_name = "City Not Found";
      $temp = "-";
      $icon = "http://openweathermap.org/img/w/01d.png";
    } else {
      // From the current_weather_json we get the city name, current temperature and current weather icon
      $city_name = $current_weather_json->name;
      $temp = round( $current_weather_json->main->temp );
      $icon = "http://openweathermap.org/img/w/" . $current_weather_json->weather[0]->icon . ".png";
    }

    // Get the 3-day forecast
    $upcoming_forecast_url = "http://api.openweathermap.org/data/2.5/forecast/daily?q=" . $location . "&units=metric&cnt=3&APPID=" . $api_key;

    // Make the API call for the 3 day forecast
    $three_day_result = file_get_contents( $upcoming_forecast_url );
    // Make the JSON from the API call into an array
    $three_day_json = json_decode( $three_day_result );

    if ( $three_day_json->cod == "404" ) {
      $one_day = "N/A";
      $two_day = "N/A";
      $thr_day = "N/A";

      $one_day_temp = "-";
      $two_day_temp = "-";
      $thr_day_temp = "-";
    } else {
      // From the three_day_json we can get the next three days week day name (eg Mon)
      $one_day = date( 'D', $three_day_json->list[0]->dt );
      $two_day = date( 'D', $three_day_json->list[1]->dt );
      $thr_day = date( 'D', $three_day_json->list[2]->dt );
      // We also get the next three days temperatures from the three_day_json
      $one_day_temp = round( $three_day_json->list[0]->temp->day );
      $two_day_temp = round( $three_day_json->list[1]->temp->day );
      $thr_day_temp = round( $three_day_json->list[2]->temp->day );
    }

    echo $args['before_widget'];
    ?>

    <section class="dobsondev-weather-app">

      <div class="current-weather">
        <p class="temp"><?php echo $temp; ?>&deg</p>
        <p class="city"><?php echo strtoupper( $city_name ); ?></p>
      </div>

      <div class="current-weather-icon">
        <img src="<?php echo $icon; ?>" />
      </div>

      <div class="three-day-forecast">
        <ul>
          <li><span class="day"><?php echo strtoupper( $one_day ); ?></span><br/><span class="temp"> <?php echo $one_day_temp; ?>&deg</span></li>
          <li><span class="day"><?php echo strtoupper( $two_day ); ?></span><br/><span class="temp"> <?php echo $two_day_temp; ?>&deg</span></li>
          <li><span class="day"><?php echo strtoupper( $thr_day ); ?></span><br/><span class="temp"> <?php echo $thr_day_temp; ?>&deg</span></li>
        </ul>
      </div>

    </section>

    <?php
    echo $args['after_widget'];
  }

} // END class dobsondev_weather_widget


/* Adds a shortcode for displaying the weather widget in the content of the site */
function dobsondev_weather_shortcode($atts) {
  extract(shortcode_atts(array(
    'location' => "", // This will return an error fromt the API call and produce the error weather app
    'align' => 'none',
  ), $atts));
  // If they leave in the example location from the readme.txt, then this will return an error fromt the API call and produce the error weather app
  if ( $location == "City/Town, CC" ) {
    $location = "";
  }
  // Alternative ways to say center just in case...
  if ( $align == "alignment" ) {
    $align = "none";
  } else if ( $align == "centered" ) {
    $align = "center";
  } else if ( $align == "centered" ) {
    $align = "center";
  }

  // Ensure the location has no spaces when we use in for the API call
  $location = str_replace( ' ', '', $location );

  // Make sure the alignment attribute is in all lower case as well
  $align = strtolower ( $align );

  // Get the API key from the settings
  $api_key = get_option( 'dobsondev_weather_api_key' );

  // Get the 3-hour forecast for current weather conditions
  $current_weather_url = "http://api.openweathermap.org/data/2.5/weather?q=" . $location . "&units=metric&APPID=" . $api_key;

  // Make the API call for the current weather
  $current_weather_result = file_get_contents( $current_weather_url );
  // Make the JSON from the API call into an array
  $current_weather_json = json_decode( $current_weather_result );

  // Couldn't find the city/something else went wrong
  if ( $current_weather_json->cod == "404" ) {
    $city_name = "City Not Found";
    $temp = "-";
    $icon = "http://openweathermap.org/img/w/01d.png";
  } else {
    // From the current_weather_json we get the city name, current temperature and current weather icon
    $city_name = $current_weather_json->name;
    $temp = round( $current_weather_json->main->temp );
    $icon = "http://openweathermap.org/img/w/" . $current_weather_json->weather[0]->icon . ".png";
  }

  // Get the 3-day forecast
  $upcoming_forecast_url = "http://api.openweathermap.org/data/2.5/forecast/daily?q=" . $location . "&units=metric&cnt=3&APPID=" . $api_key;

  // Make the API call for the 3 day forecast
  $three_day_result = file_get_contents( $upcoming_forecast_url );
  // Make the JSON from the API call into an array
  $three_day_json = json_decode( $three_day_result );

  if ( $three_day_json->cod == "404" ) {
    $one_day = "N/A";
    $two_day = "N/A";
    $thr_day = "N/A";

    $one_day_temp = "-";
    $two_day_temp = "-";
    $thr_day_temp = "-";
  } else {
    // From the three_day_json we can get the next three days week day name (eg Mon)
    $one_day = date( 'D', $three_day_json->list[0]->dt );
    $two_day = date( 'D', $three_day_json->list[1]->dt );
    $thr_day = date( 'D', $three_day_json->list[2]->dt );
    // We also get the next three days temperatures from the three_day_json
    $one_day_temp = round( $three_day_json->list[0]->temp->day );
    $two_day_temp = round( $three_day_json->list[1]->temp->day );
    $thr_day_temp = round( $three_day_json->list[2]->temp->day );
  }

  $html = '
  <div class="dobsondev-weather-shortcode dobsondev-weather-align-' . $align . '">
    <section class="dobsondev-weather-app">
      <div class="current-weather">
        <p class="temp">' . $temp . '&deg</p>
        <p class="city">' . strtoupper( $city_name ) . '</p>
      </div>
      <div class="current-weather-icon">
        <img src="' . $icon . '" />
      </div>
      <div class="three-day-forecast">
        <ul>
          <li><span class="day">' . strtoupper( $one_day ) . '</span><br/><span class="temp"> ' . $one_day_temp . '&deg</span></li>
          <li><span class="day">' . strtoupper( $two_day ) . '</span><br/><span class="temp"> ' . $two_day_temp . '&deg</span></li>
          <li><span class="day">' . strtoupper( $thr_day ) . '</span><br/><span class="temp"> ' . $thr_day_temp . '&deg</span></li>
        </ul>
      </div>
    </section>
  </div>
  ';
  return $html;
}
add_shortcode('weather', 'dobsondev_weather_shortcode');

?>