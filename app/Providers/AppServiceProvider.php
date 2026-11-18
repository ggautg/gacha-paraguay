<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de servicios principal de la aplicación.
 *
 * Hoy no registra ni arranca nada: se conserva como el lugar previsto para enlazar
 * interfaces en el contenedor y para el código de arranque compartido del proyecto.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los enlaces del contenedor de servicios.
     *
     * Se deja vacío porque la aplicación aún no tiene interfaces que resolver;
     * conviene mantener el punto de extensión que Laravel invoca en el arranque.
     */
    public function register(): void
    {
        //
    }

    /**
     * Ejecuta el código de arranque posterior al registro de los proveedores.
     *
     * Se deja vacío a propósito: las reglas del juego viven en config/gachapon.php
     * y no requieren inicialización imperativa.
     */
    public function boot(): void
    {
        //
    }
}
