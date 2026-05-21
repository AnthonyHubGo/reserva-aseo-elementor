<?php

if (!defined('ABSPATH')) exit;

add_action('elementor/widgets/register', function ($widgets_manager) {
  require_once RAE_PATH . 'includes/widget-reserva-aseo.php';
  $widgets_manager->register(new \RAE_Widget_Reserva_Aseo());
});