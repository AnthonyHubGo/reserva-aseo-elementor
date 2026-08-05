<?php

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;

class RAE_Widget_Reserva_Aseo extends Widget_Base {

  public function get_name() {
    return 'reserva_aseo';
  }

  public function get_title() {
    return 'Reserva de Aseo';
  }

  public function get_icon() {
    return 'eicon-calendar';
  }

  public function get_categories() {
    return ['general'];
  }

  public function get_style_depends() {
    return ['rae-nunito', 'rae-css'];
  }

  public function get_script_depends() {
    return ['rae-js'];
  }

  protected function render() {
    $personal = get_posts([
      'post_type' => 'personal_aseo',
      'numberposts' => -1,
      'post_status' => 'publish',
    ]);

    if (function_exists('rae_personal_aseo_disponible')) {
      $personal = array_values(array_filter($personal, function ($persona) {
        return rae_personal_aseo_disponible($persona->ID);
      }));
    }
    ?>

    <form id="rae-form" class="rae-form" novalidate>
      <header class="rae-wizard-header">
        <img
          class="rae-logo"
          src="<?php echo esc_url(RAE_URL . 'assets/images/LogoSAT.webp'); ?>"
          alt="SAT Soluciones a Tiempo"
          width="230"
          height="100"
          decoding="async"
        >
        <div class="rae-wizard-heading">
          <span>Reserva en línea</span>
          <h2>Agenda tu servicio de aseo</h2>
          <p>Completa los tres pasos para continuar al pago seguro.</p>
        </div>
      </header>

      <ol class="rae-stepper" aria-label="Progreso de la reserva">
        <li class="is-active" data-step-indicator="1" aria-current="step">
          <span>1</span>
          <small>Cliente</small>
        </li>
        <li data-step-indicator="2">
          <span>2</span>
          <small>Servicio</small>
        </li>
        <li data-step-indicator="3">
          <span>3</span>
          <small>Ubicación</small>
        </li>
      </ol>

      <div class="rae-wizard-content">
        <div class="rae-no-availability-alert" role="alert" aria-live="polite" <?php echo empty($personal) ? '' : 'hidden'; ?>>
          No se puede reservar este día porque no hay personal disponible.
        </div>

        <section class="rae-step-panel is-active" data-step-panel="1" aria-labelledby="rae-step-title-1">
          <div class="rae-panel-heading">
            <span>Paso 1 de 3</span>
            <h3 id="rae-step-title-1">Cuéntanos quién eres</h3>
            <p>Estos datos se usarán para enviarte la información de la reserva.</p>
          </div>

          <div class="rae-fields-grid">
            <label class="rae-field rae-field--wide">
              <span>Nombre completo</span>
              <input type="text" name="nombre" placeholder="Ej. Ana Martínez" autocomplete="name" required>
            </label>

            <label class="rae-field">
              <span>Correo electrónico</span>
              <input type="email" name="email" placeholder="nombre@correo.com" autocomplete="email" required>
            </label>

            <label class="rae-field">
              <span>Teléfono de contacto</span>
              <input type="tel" name="telefono" placeholder="300 000 0000" autocomplete="tel" inputmode="tel" pattern="[0-9+() -]{7,30}" required>
            </label>
          </div>

          <div class="rae-step-actions rae-step-actions--end">
            <button type="button" class="rae-button rae-button--primary" data-next-step="2">
              Continuar
              <span aria-hidden="true">→</span>
            </button>
          </div>
        </section>

        <section class="rae-step-panel" data-step-panel="2" aria-labelledby="rae-step-title-2" hidden>
          <div class="rae-panel-heading">
            <span>Paso 2 de 3</span>
            <h3 id="rae-step-title-2">Elige tu servicio</h3>
            <p>Selecciona una fecha, la jornada y la persona que prefieres.</p>
          </div>

          <div class="rae-fields-grid rae-service-fields">
            <label class="rae-field">
              <span>Fecha del servicio</span>
              <span class="rae-date-field">
                <input type="text" name="fecha" placeholder="Selecciona la fecha" autocomplete="off" required>
              </span>
            </label>

            <label class="rae-field">
              <span>Jornada</span>
              <select name="jornada" required>
                <option value="">Selecciona jornada</option>
                <option value="manana">Mañana</option>
                <option value="tarde">Tarde</option>
                <option value="completa">Jornada completa</option>
              </select>
            </label>
          </div>

          <div class="rae-personal-heading">
            <h4>Selecciona el personal</h4>
            <p>La disponibilidad se actualiza según la fecha y jornada elegidas.</p>
          </div>

          <div class="rae-personal-carousel">
            <button type="button" class="rae-carousel-arrow rae-carousel-prev" aria-label="Ver personal anterior">
              &#8249;
            </button>

            <div class="rae-personal-viewport">
              <div class="rae-personal-grid">
                <?php foreach ($personal as $persona): ?>
                  <?php
                  $fechas_no_disponibles = get_post_meta($persona->ID, '_rae_fechas_no_disponibles', true);

                  if (!is_array($fechas_no_disponibles)) {
                    $fechas_no_disponibles = [];
                  }

                  $ocupaciones = function_exists('rae_obtener_ocupaciones_personal')
                    ? rae_obtener_ocupaciones_personal($persona->ID)
                    : [];
                  ?>

                  <label
                    class="rae-person-card"
                    data-unavailable-dates="<?php echo esc_attr(wp_json_encode(array_values($fechas_no_disponibles))); ?>"
                    data-occupations="<?php echo esc_attr(wp_json_encode($ocupaciones)); ?>"
                  >
                    <input type="radio" name="persona_id" value="<?php echo esc_attr($persona->ID); ?>" required>
                    <span class="rae-person-selected" aria-hidden="true">✓</span>

                    <span class="rae-person-photo">
                      <?php
                      if (has_post_thumbnail($persona->ID)) {
                        echo get_the_post_thumbnail($persona->ID, 'thumbnail');
                      } else {
                        echo '<span class="rae-no-photo">Sin foto</span>';
                      }
                      ?>
                    </span>

                    <strong><?php echo esc_html($persona->post_title); ?></strong>
                    <small>Personal de aseo</small>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="button" class="rae-carousel-arrow rae-carousel-next" aria-label="Ver personal siguiente">
              &#8250;
            </button>
          </div>

          <p class="rae-no-personal-date" hidden>No hay personal disponible para la fecha y jornada seleccionadas.</p>

          <div class="rae-step-actions">
            <button type="button" class="rae-button rae-button--secondary" data-previous-step="1">
              <span aria-hidden="true">←</span>
              Volver
            </button>
            <button type="button" class="rae-button rae-button--primary" data-next-step="3">
              Continuar
              <span aria-hidden="true">→</span>
            </button>
          </div>
        </section>

        <section class="rae-step-panel" data-step-panel="3" aria-labelledby="rae-step-title-3" hidden>
          <div class="rae-panel-heading">
            <span>Paso 3 de 3</span>
            <h3 id="rae-step-title-3">¿Dónde será el servicio?</h3>
            <p>Ingresa la ubicación exacta para que el personal pueda llegar sin inconvenientes.</p>
          </div>

          <div class="rae-address-fields rae-fields-grid">
            <label class="rae-field">
              <span>Ciudad</span>
              <input type="text" name="ciudad" placeholder="Ej. Cali" autocomplete="address-level2" required>
            </label>

            <label class="rae-field">
              <span>Barrio</span>
              <input type="text" name="barrio" placeholder="Ej. San Fernando" autocomplete="address-level3" required>
            </label>

            <label class="rae-field rae-field--wide">
              <span>Dirección completa</span>
              <input type="text" name="direccion" placeholder="Calle, carrera, número y apartamento si aplica" autocomplete="street-address" minlength="4" required>
            </label>

            <label class="rae-field rae-field--wide">
              <span>Tipo de vivienda</span>
              <select name="casa" required>
                <option value="">Selecciona una opción</option>
                <option value="Casa">Casa</option>
                <option value="Apartamento">Apartamento</option>
              </select>
            </label>
          </div>

          <div class="rae-step-actions">
            <button type="button" class="rae-button rae-button--secondary" data-previous-step="2">
              <span aria-hidden="true">←</span>
              Volver
            </button>
            <button type="submit" class="rae-button rae-button--primary">
              Reservar y continuar al pago
              <span aria-hidden="true">→</span>
            </button>
          </div>
        </section>

        <div id="rae-msg" role="status" aria-live="polite"></div>
      </div>
    </form>

    <?php
  }
}
