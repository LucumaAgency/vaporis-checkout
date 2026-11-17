<?php
/**
 * Vaporis · Campos de checkout: DNI + etiquetas.
 *
 * NO es un plugin suelto: lo carga vaporis-checkout-pe.php.
 * (Antes tenía cabecera propia y aparecían dos plugins en la lista, con el
 *  riesgo de activar solo uno. Ahora hay un único plugin.)
 */

if ( ! defined('ABSPATH') ) exit;

/** Longitud exacta del DNI peruano. Si aceptas Carné de Extranjería, ver VAPORIS_DNI_MAX */
if ( ! defined('VAPORIS_DNI_MIN') ) define('VAPORIS_DNI_MIN', 8);
if ( ! defined('VAPORIS_DNI_MAX') ) define('VAPORIS_DNI_MAX', 8);  // súbelo a 12 para admitir CE
if ( ! defined('VAPORIS_DNI_REQUIRED') ) define('VAPORIS_DNI_REQUIRED', true);

/**
 * 1) Añadir el campo al checkout, dentro del bloque de facturación,
 *    justo después del teléfono (priority 25 va entre phone 20 y email 30).
 */
add_filter('woocommerce_checkout_fields', 'vaporis_dni_add_field');
function vaporis_dni_add_field($fields) {
    $fields['billing']['billing_dni'] = array(
        'type'        => 'text',
        'label'       => __('DNI', 'vaporis'),
        'placeholder' => __('12345678', 'vaporis'),
        'required'    => (bool) VAPORIS_DNI_REQUIRED,
        'class'       => array('form-row-wide', 'vaporis-dni-field'),
        'priority'    => 25,
        'clear'       => true,
        'maxlength'   => VAPORIS_DNI_MAX,
        'custom_attributes' => array(
            'inputmode' => 'numeric',
            'autocomplete' => 'off',
            'pattern'   => '[0-9]{' . VAPORIS_DNI_MIN . ',' . VAPORIS_DNI_MAX . '}',
        ),
    );
    return $fields;
}

/** 2) También en la dirección de facturación de "Mi cuenta" (para que se guarde en el perfil) */
add_filter('woocommerce_billing_fields', 'vaporis_dni_account_field');
function vaporis_dni_account_field($fields) {
    if ( is_admin() || ! isset($fields['billing_phone']) ) return $fields;
    $fields['billing_dni'] = array(
        'label'    => __('DNI', 'vaporis'),
        'required' => (bool) VAPORIS_DNI_REQUIRED,
        'class'    => array('form-row-wide'),
        'priority' => 25,
    );
    return $fields;
}

/** 3) Validación: solo dígitos y longitud exacta */
add_action('woocommerce_after_checkout_validation', 'vaporis_dni_validate', 10, 2);
function vaporis_dni_validate($data, $errors) {
    $dni = isset($data['billing_dni']) ? trim($data['billing_dni']) : '';

    if ( $dni === '' ) {
        // Si es obligatorio, Woo ya emite su propio error de campo requerido.
        return;
    }
    if ( ! preg_match('/^[0-9]+$/', $dni) ) {
        $errors->add('billing_dni', __('El DNI solo puede contener números.', 'vaporis'));
        return;
    }
    $len = strlen($dni);
    if ( $len < VAPORIS_DNI_MIN || $len > VAPORIS_DNI_MAX ) {
        $errors->add('billing_dni', sprintf(
            _n('El DNI debe tener %d dígito.', 'El DNI debe tener %d dígitos.', VAPORIS_DNI_MAX, 'vaporis'),
            VAPORIS_DNI_MAX
        ));
    }
}

/** 4) Guardar en el pedido (HPOS-safe: se usa el objeto $order, no update_post_meta) */
add_action('woocommerce_checkout_create_order', 'vaporis_dni_save_to_order', 10, 2);
function vaporis_dni_save_to_order($order, $data) {
    $dni = isset($_POST['billing_dni']) ? sanitize_text_field(wp_unslash($_POST['billing_dni'])) : '';
    if ( $dni !== '' ) {
        $order->update_meta_data('_billing_dni', $dni);
    }
}

/** 5) Mostrarlo en el admin del pedido */
add_action('woocommerce_admin_order_data_after_billing_address', 'vaporis_dni_admin_display');
function vaporis_dni_admin_display($order) {
    $dni = $order->get_meta('_billing_dni');
    if ( $dni ) {
        echo '<p><strong>' . esc_html__('DNI', 'vaporis') . ':</strong> ' . esc_html($dni) . '</p>';
    }
}

/** 6) Hacerlo editable desde el admin (campo del panel de facturación) */
add_filter('woocommerce_admin_billing_fields', 'vaporis_dni_admin_field');
function vaporis_dni_admin_field($fields) {
    $fields['dni'] = array(
        'label' => __('DNI', 'vaporis'),
        'show'  => true,
    );
    return $fields;
}

/** 7) Incluirlo en los emails de pedido y en la vista de pedido del cliente */
add_filter('woocommerce_order_details_after_customer_details', 'vaporis_dni_customer_display');
function vaporis_dni_customer_display($order) {
    $dni = $order->get_meta('_billing_dni');
    if ( $dni ) {
        echo '<p><strong>' . esc_html__('DNI', 'vaporis') . ':</strong> ' . esc_html($dni) . '</p>';
    }
}

add_filter('woocommerce_email_order_meta_fields', 'vaporis_dni_email_field', 10, 3);
function vaporis_dni_email_field($fields, $sent_to_admin, $order) {
    $dni = $order->get_meta('_billing_dni');
    if ( $dni ) {
        $fields['billing_dni'] = array(
            'label' => __('DNI', 'vaporis'),
            'value' => $dni,
        );
    }
    return $fields;
}

/* =========================================================================
   ETIQUETAS DE CAMPOS
   "Población" es la traducción es_ES del campo nativo `city` de WooCommerce
   ("Town / City"). No es un campo personalizado: se renombra, no se crea.
   Se usa `woocommerce_default_address_fields` porque cubre de una sola vez
   facturación, envío y las direcciones de Mi cuenta.
   ========================================================================= */

if ( ! defined('VAPORIS_LABEL_CITY') ) define('VAPORIS_LABEL_CITY', 'Distrito');

/**
 * Interruptor: ponlo en false si instalas un plugin de ubigeo peruano
 * (departamento/provincia/distrito). Esos plugins gestionan `city` ellos mismos
 * y renombrarlo aquí puede pisarles la etiqueta o duplicar el concepto.
 */
if ( ! defined('VAPORIS_LABEL_CITY_ENABLE') ) define('VAPORIS_LABEL_CITY_ENABLE', false); // false porque vaporis-ubigeo.php ya pone la etiqueta "Distrito"

/** 9) "Población" → "Distrito" en checkout, envío y Mi cuenta */
add_filter('woocommerce_default_address_fields', 'vaporis_label_distrito', 20);
function vaporis_label_distrito($fields) {
    if ( ! VAPORIS_LABEL_CITY_ENABLE ) return $fields;
    if ( isset($fields['city']) ) {
        $fields['city']['label']       = __(VAPORIS_LABEL_CITY, 'vaporis');
        $fields['city']['placeholder'] = __('Miraflores', 'vaporis');
    }
    return $fields;
}

/** 10) Y en el panel de administración del pedido, para que no quede desalineado */
add_filter('woocommerce_admin_billing_fields', 'vaporis_label_distrito_admin_billing', 20);
function vaporis_label_distrito_admin_billing($fields) {
    if ( VAPORIS_LABEL_CITY_ENABLE && isset($fields["city"]) ) $fields["city"]["label"] = __(VAPORIS_LABEL_CITY, "vaporis");
    return $fields;
}

add_filter('woocommerce_admin_shipping_fields', 'vaporis_label_distrito_admin_shipping', 20);
function vaporis_label_distrito_admin_shipping($fields) {
    if ( VAPORIS_LABEL_CITY_ENABLE && isset($fields["city"]) ) $fields["city"]["label"] = __(VAPORIS_LABEL_CITY, "vaporis");
    return $fields;
}

/** 8) Compatibilidad HPOS (WooCommerce High-Performance Order Storage) */
add_action('before_woocommerce_init', function () {
    if ( class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', __FILE__, true
        );
    }
});
