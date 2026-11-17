# Vaporis · Checkout Perú

Plugin de WooCommerce para vaporismaximus.com. Punto de entrada: `vaporis-checkout-pe.php` (en la raíz).

- **DNI** (`vaporis-checkout-dni.php`): campo `billing_dni` obligatorio, 8 dígitos, guardado en el pedido.
- **Departamento → Distrito** (`vaporis-ubigeo.php` + `assets/` + `data/ubigeo-peru.json`): el distrito es un select con los 1835 distritos; se guardan provincia y código de ubigeo en el pedido. Requiere checkout **clásico** (`[woocommerce_checkout]`), no el de bloques.
- **Delivery por distrito** (`vaporis-delivery.php`, desde 2.3.0): método de envío "Delivery Vaporis".

| Grupo | Costo | Gratis desde |
|---|---|---|
| Lima A (céntricos de Lima y Callao) | S/ 15 | S/ 499 |
| Lima B (Ancón, Santa Rosa, Ventanilla, Mi Perú, Puente Piedra, Carabayllo, Chosica, Chaclacayo, Cieneguilla, Pachacámac, Lurín, balnearios del sur) | S/ 20 | S/ 499 |
| Provincias (todo lo demás, incluidas Huaral, Cañete, Huaura, Barranca) | S/ 25 | S/ 699 |

Costos y mínimos se editan en WooCommerce → Ajustes → Envío → zona → Delivery Vaporis. Los distritos de cada grupo, por código de ubigeo, en `vaporis-delivery.php`. El mínimo es inclusivo y se mide con los cupones descontados.

`tools/gen_ubigeo.py` regenera `data/ubigeo-peru.json` (no se ejecuta en el sitio). El panel de diagnóstico `vaporis-debug.php` no va en el repo: expone la lista de plugins activos.

Documentación completa: `DOCUMENTACION.md` de la carpeta del proyecto Vaporis (§5-bis y checkout).
