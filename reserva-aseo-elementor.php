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
define('RAE_DB_VERSION', '1.1.0');

require_once RAE_PATH . 'includes/db.php';
require_once RAE_PATH . 'includes/activator.php';
require_once RAE_PATH . 'includes/personal-cpt.php';
require_once RAE_PATH . 'includes/elementor-widget.php';
require_once RAE_PATH . 'includes/email-templates.php';
require_once RAE_PATH . 'includes/ajax.php';
require_once RAE_PATH . 'includes/admin-reservas.php';

register_activation_hook(__FILE__, ['RAE_Activator', 'activate']);

add_action('plugins_loaded', function () {
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
    'rae-css',
    RAE_URL . 'assets/css/style.css',
    ['rae-nunito'],
    '1.0.6'
  );

  wp_register_script(
    'rae-js',
    RAE_URL . 'assets/js/app.js',
    [],
    '1.0.6',
    true
  );

  wp_localize_script('rae-js', 'rae_ajax', [
    'ajax_url' => admin_url('admin-ajax.php')
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
