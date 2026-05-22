<?php
/**
 * Plugin Name: Reserva de Aseo Elementor
 * Description: Sistema de reservas para personal de aseo doméstico con Elementor.
 * Version: 1.0.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) exit;

define('RAE_PATH', plugin_dir_path(__FILE__));
define('RAE_URL', plugin_dir_url(__FILE__));
define('RAE_DB_VERSION', '1.1.1');

require_once RAE_PATH . 'includes/db.php';
require_once RAE_PATH . 'includes/roles.php';
require_once RAE_PATH . 'includes/activator.php';
require_once RAE_PATH . 'includes/personal-cpt.php';
require_once RAE_PATH . 'includes/elementor-widget.php';
require_once RAE_PATH . 'includes/email-templates.php';
require_once RAE_PATH . 'includes/holidays.php';
require_once RAE_PATH . 'includes/ajax.php';
require_once RAE_PATH . 'includes/admin-reservas.php';
require_once RAE_PATH . 'includes/admin-settings.php';

register_activation_hook(__FILE__, ['RAE_Activator', 'activate']);

add_action('plugins_loaded', function () {
  if (function_exists('rae_registrar_roles_y_capabilities')) {
    rae_registrar_roles_y_capabilities();
  }

  if (get_option('rae_db_version') === RAE_DB_VERSION) {
    return;
  }

  RAE_DB::create_table();
  update_option('rae_db_version', RAE_DB_VERSION);
});

function rae_register_reserva_assets() {
  wp_register_style(
    'rae-nunito',
    'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap',
    [],
    null
  );

  wp_register_style(
    'rae-flatpickr',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    [],
    '4.6.13'
  );

  wp_register_style(
    'rae-css',
    RAE_URL . 'assets/css/style.css',
    ['rae-nunito', 'rae-flatpickr'],
    '1.1.1'
  );

  wp_register_script(
    'rae-flatpickr',
    'https://cdn.jsdelivr.net/npm/flatpickr',
    [],
    '4.6.13',
    true
  );

  wp_register_script(
    'rae-js',
    RAE_URL . 'assets/js/app.js',
    ['rae-flatpickr'],
    '1.1.1',
    true
  );

  $today = wp_date('Y-m-d');
  $current_year = (int) wp_date('Y');

  wp_localize_script('rae-js', 'rae_ajax', [
    'ajax_url' => admin_url('admin-ajax.php'),
  ]);

  wp_localize_script('rae-js', 'raeReservaConfig', [
    'today' => $today,
    'holidays' => function_exists('rae_festivos_colombia_rango')
      ? rae_festivos_colombia_rango($current_year, $current_year + 5)
      : [],
  ]);
}

add_action('wp_enqueue_scripts', function () {
  rae_register_reserva_assets();
  wp_enqueue_style('rae-css');
  wp_enqueue_script('rae-js');
});

add_action('elementor/frontend/after_register_styles', function () {
  rae_register_reserva_assets();
});

add_action('elementor/frontend/after_register_scripts', function () {
  rae_register_reserva_assets();
});
