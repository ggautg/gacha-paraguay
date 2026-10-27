<?php

namespace App\Support;

class Salary
{
    /**
     * Valor del sueldo mínimo legal vigente, en guaraníes.
     */
    public static function value(): int
    {
        return (int) config('gachapon.salary.amount');
    }

    /**
     * Formatea un monto en guaraníes como sueldos mínimos (por ejemplo "1 SM" o "0,2 SM").
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
     */
    public static function guaranies(int $amount): string
    {
        return '₲ '.number_format($amount, 0, ',', '.');
    }
}
