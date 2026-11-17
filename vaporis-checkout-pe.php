<?php
/**
 * Plugin Name: Vaporis · Checkout Perú (DNI + Distritos)
 * Description: Campo DNI validado y desplegable de Distrito encadenado al Departamento, con los 1835 distritos del Perú. Guarda provincia y código de ubigeo en el pedido.
 * Version:     2.3.0
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

define('VAPORIS_CHECKOUT_PE_DIR', __DIR__);

require_once __DIR__ . '/vaporis-checkout-dni.php';
require_once __DIR__ . '/vaporis-ubigeo.php';
require_once __DIR__ . '/vaporis-delivery.php';
// El zip de producción no trae el panel de debug.
if ( is_readable(__DIR__ . '/vaporis-debug.php') ) require_once __DIR__ . '/vaporis-debug.php';
