<?php

namespace App\Support;

/**
 * Utilidad de formato para expresar montos del juego en términos del salario mínimo.
 *
 * Lee el valor de la configuración para que los textos visibles sigan al sueldo
 * vigente sin necesidad de editar plantillas cuando cambia el decreto.
 */
class Salary
{
    /**
     * Valor del sueldo mínimo legal vigente, en guaraníes.
     *
     * Se toma de la configuración, que referencia el decreto aplicable, en lugar de
     * fijarlo en código: actualizar la economía del juego requiere cambiar un solo archivo.
     */
    public static function value(): int
    {
        return (int) config('gachapon.salary.amount');
    }

    /**
     * Formatea un monto en guaraníes como sueldos mínimos (por ejemplo "1 SM" o "0,2 SM").
     *
     * El doble rtrim quita los ceros decimales y la coma sobrante para que "1,00 SM"
     * se lea simplemente "1 SM", más natural en la interfaz.
     */
    public static function format(int $guaranies): string
    {
        $value = self::value();
        $sueldos = $value > 0 ? $guaranies / $value : 0;

        $label = rtrim(rtrim(number_format($sueldos, 2, ',', '.'), '0'), ',');

        return "{$label} SM";
    }

    /**
     * Formatea un monto en guaraníes con su símbolo y separador de miles.
     *
     * Se usa coma para los decimales y punto para los miles, el criterio habitual
     * de escritura de números en Paraguay.
     */
    public static function guaranies(int $amount): string
    {
        return '₲ '.number_format($amount, 0, ',', '.');
    }
}
