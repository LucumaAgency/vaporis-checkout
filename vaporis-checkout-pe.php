<?php
/**
 * Plugin Name: Vaporis · Checkout Perú (DNI + Distritos)
 * Description: Campo DNI validado y desplegable de Distrito encadenado al Departamento, con los 1835 distritos del Perú. Guarda provincia y código de ubigeo en el pedido.
 * Version:     2.3.1
 * Author:      Lucuma Agency
 * Text Domain: vaporis
 * Requires Plugins: woocommerce
 *
 * ÚNICO punto de entrada. Carga:
 *   - vaporis-checkout-dni.php  (campo DNI + etiquetas)
 *   - vaporis-ubigeo.php        (Departamento -> Distrito)
 *   - vaporis-delivery.php      (método de envío por distrito: Lima A / Lima B / Provincia)
 *   - vaporis-debug.php         (panel de diagnóstico: ?vaporis_debug=1)
 *
 * Estructura de campos (v2, sin provincia):
 *   Departamento -> campo NATIVO `state` de Woo
 *   Distrito     -> campo NATIVO `city`, convertido de texto a <select>
 *
 * La provincia ya no se le pide al cliente: se deduce del distrito elegido y se
 * guarda igual en el pedido, junto al código de ubigeo.
 */

if ( ! defined('ABSPATH') ) exit;

// Si otra copia del plugin ya está cargada (p. ej. la del zip en vaporis-checkout-pe/
// y la de Deployer for Git en vaporis-checkout/), esta no hace nada: evita el error
// fatal por clases duplicadas mientras conviven durante el cambio.
if ( defined('VAPORIS_CHECKOUT_PE_DIR') ) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-warning"><p><strong>Vaporis · Checkout Perú</strong>: hay dos copias activas. Funciona la de <code>'
           . esc_html( basename(VAPORIS_CHECKOUT_PE_DIR) ) . '</code>; desactiva y borra la que sobra.</p></div>';
    });
    return;
}

define('VAPORIS_CHECKOUT_PE_DIR', __DIR__);

require_once __DIR__ . '/vaporis-checkout-dni.php';
require_once __DIR__ . '/vaporis-ubigeo.php';
require_once __DIR__ . '/vaporis-delivery.php';
// El zip de producción no trae el panel de debug.
if ( is_readable(__DIR__ . '/vaporis-debug.php') ) require_once __DIR__ . '/vaporis-debug.php';
