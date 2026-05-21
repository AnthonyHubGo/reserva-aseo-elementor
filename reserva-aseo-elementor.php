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

require_once RAE_PATH . 'includes/db.php';
require_once RAE_PATH . 'includes/activator.php';
require_once RAE_PATH . 'includes/personal-cpt.php';
require_once RAE_PATH . 'includes/elementor-widget.php';
require_once RAE_PATH . 'includes/ajax.php';
require_once RAE_PATH . 'includes/admin-reservas.php';

register_activation_hook(__FILE__, ['RAE_Activator', 'activate']);

add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style(
    'rae-css',
    RAE_URL . 'assets/css/style.css',
    [],
    '1.0.0'
  );

  wp_enqueue_script(
    'rae-js',
    RAE_URL . 'assets/js/app.js',
    [],
    '1.0.0',
    true
  );

  wp_localize_script('rae-js', 'rae_ajax', [
    'ajax_url' => admin_url('admin-ajax.php')
  ]);
});