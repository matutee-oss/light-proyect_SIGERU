<?php

/**
 * Funciones de validación reutilizables (sin acceso a la base de datos,
 * por eso se pueden probar con tests unitarios puros).
 */
class Validacion
{
    public const ESTADOS_CONTENEDOR = ['funcional', 'roto', 'desbordado'];
    public const ESTADOS_CAMION = ['disponible', 'en_ruta', 'mantenimiento', 'fuera_servicio'];
    public const TIPOS_INCIDENCIA = ['rotura', 'desborde', 'falta_recoleccion', 'otro'];
    public const ESTADOS_INCIDENCIA = ['abierta', 'en_curso', 'resuelta'];
    public const PRIORIDADES = ['baja', 'media', 'alta'];
    public const ROLES = ['administrador', 'cuadrilla', 'operario', 'vecino'];
    public const ESTADOS_USUARIO = ['activo', 'pendiente', 'inactivo'];

    /** Devuelve el primer campo obligatorio vacío, o null si están todos. */
    public static function faltante(array $datos, array $campos): ?string
    {
        foreach ($campos as $campo) {
            if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === '') {
                return $campo;
            }
        }
        return null;
    }

    public static function enLista($valor, array $permitidos): bool
    {
        return in_array($valor, $permitidos, true);
    }

    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Cédula uruguaya con dígito verificador (algoritmo oficial 2987634).
     * Acepta puntos y guiones: "5.748.431-2" o "57484312".
     */
    public static function cedulaUruguaya(string $ci): bool
    {
        $digitos = preg_replace('/\D/', '', $ci);
        if (strlen($digitos) < 7 || strlen($digitos) > 8) {
            return false;
        }

        $digitos = str_pad($digitos, 8, '0', STR_PAD_LEFT);
        $factores = [2, 9, 8, 7, 6, 3, 4];
        $suma = 0;
        for ($i = 0; $i < 7; $i++) {
            $suma += (int) $digitos[$i] * $factores[$i];
        }
        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int) $digitos[7];
    }

    /** Solo dígitos, como se guarda en la base. */
    public static function normalizarCedula(string $ci): string
    {
        return preg_replace('/\D/', '', $ci);
    }

    /** Coordenadas opcionales: ambas vacías, o ambas válidas. */
    public static function coordenadas($latitud, $longitud): bool
    {
        $latVacia = $latitud === null || $latitud === '';
        $lonVacia = $longitud === null || $longitud === '';
        if ($latVacia && $lonVacia) {
            return true;
        }
        if ($latVacia || $lonVacia || !is_numeric($latitud) || !is_numeric($longitud)) {
            return false;
        }
        return (float) $latitud >= -90 && (float) $latitud <= 90
            && (float) $longitud >= -180 && (float) $longitud <= 180;
    }

    public static function porcentaje($valor): bool
    {
        return is_numeric($valor) && (float) $valor >= 0 && (float) $valor <= 100;
    }

    public static function numeroPositivo($valor, bool $permitirCero = false): bool
    {
        if (!is_numeric($valor)) {
            return false;
        }
        return $permitirCero ? (float) $valor >= 0 : (float) $valor > 0;
    }

    /** Fecha en formato AAAA-MM-DD. */
    public static function fecha(?string $fecha): bool
    {
        if ($fecha === null || $fecha === '') {
            return false;
        }
        $objeto = DateTime::createFromFormat('Y-m-d', $fecha);
        return $objeto !== false && $objeto->format('Y-m-d') === $fecha;
    }

    public static function contrasena(string $password): bool
    {
        return strlen($password) >= 8;
    }

    /** Matrícula uruguaya: 3 letras + 3/4 números (ej. "SBC 1234"). */
    public static function matricula(string $matricula): bool
    {
        return (bool) preg_match('/^[A-Z]{3}\s?\d{3,4}$/i', trim($matricula));
    }

    /** Valor nulo si viene vacío; si no, el número. */
    public static function numeroONulo($valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    public static function enteroONulo($valor): ?int
    {
        return ($valor === null || $valor === '') ? null : (int) $valor;
    }
}
