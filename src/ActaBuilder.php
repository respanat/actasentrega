<?php

/**
 * Lógica de dominio del acta. No depende de GLPI para poder probarla de forma aislada.
 *
 * Basada en la clase propuesta en el Plan de Desarrollo (Calderón, 2026) y ampliada con
 * la regla de consistencia por tipo de acta y la selección explícita de campos del contenido canónico.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Actasentrega;

final class ActaBuilder
{
    public const TIPO_ENTREGA    = 'entrega';
    public const TIPO_DEVOLUCION = 'devolucion';
    public const TIPO_TRASLADO   = 'traslado';
    public const TIPOS = [self::TIPO_ENTREGA, self::TIPO_DEVOLUCION, self::TIPO_TRASLADO];

    /** Campos que deben estar diligenciados en cada activo (indicador de completitud). */
    public const CAMPOS_OBLIGATORIOS = ['itemtype', 'items_id', 'nombre', 'serial', 'otherserial', 'estado_fisico'];

    /** Campos del activo que entran al contenido canónico (y por tanto a la huella). */
    private const CAMPOS_CANONICOS = ['itemtype', 'items_id', 'nombre', 'serial', 'otherserial', 'fabricante', 'modelo', 'estado_fisico'];

    /**
     * Versión del formato canónico. La 1 (v0.1.0) solo tenía los datos básicos; la 2 agrega las
     * especificaciones técnicas de cada activo, el cargo, la dependencia y la ubicación de las
     * personas, y los accesorios. Las actas guardan su versión, así que las antiguas se siguen
     * verificando con el formato con que se generaron.
     */
    public const VERSION_ACTUAL = 2;

    /** Especificaciones técnicas que se copian de GLPI (orden de presentación). */
    public const DETALLE = [
        'tipo_equipo'       => 'Tipo de equipo',
        'procesador'        => 'Procesador',
        'memoria'           => 'Memoria RAM',
        'almacenamiento'    => 'Almacenamiento',
        'sistema_operativo' => 'Sistema operativo',
        'direccion_ip'      => 'Dirección IP',
        'pantalla'          => 'Tamaño de pantalla',
    ];

    /** Datos de cada persona del acta, además del nombre. */
    public const DATOS_PERSONA = ['cargo', 'dependencia', 'ubicacion'];

    private const ETIQUETAS = [
        'nombre'        => 'nombre',
        'serial'        => 'número de serie',
        'otherserial'   => 'número de inventario',
        'estado_fisico' => 'estado físico',
        'itemtype'      => 'tipo de activo',
        'items_id'      => 'identificador',
    ];

    /**
     * Valida el acta antes de generarla.
     *
     * Estructura esperada:
     *  tipo, entrega['id'], recibe['id'], activos[] con los campos obligatorios y
     *  users_id_asignado (a quién tiene asignado GLPI el activo en este momento).
     *
     * @return string[] lista de errores (vacía si el acta es válida)
     */
    public static function validar(array $d): array
    {
        $e = [];
        $tipo = $d['tipo'] ?? '';
        if (!in_array($tipo, self::TIPOS, true)) {
            $e[] = 'Tipo de acta no válido.';
        }

        $entrega = $d['entrega']['id'] ?? 0;
        $recibe  = $d['recibe']['id'] ?? 0;
        if (!is_int($entrega) || $entrega <= 0) {
            $e[] = 'Falta quién entrega.';
        }
        if (!is_int($recibe) || $recibe <= 0) {
            $e[] = 'Falta quién recibe.';
        }
        if ($entrega > 0 && $entrega === $recibe) {
            $e[] = 'Quien entrega y quien recibe deben ser personas distintas.';
        }

        if (empty($d['activos']) || !is_array($d['activos'])) {
            $e[] = 'El acta debe incluir al menos un activo.';
            return $e;
        }

        $vistos = [];
        foreach (array_values($d['activos']) as $i => $a) {
            $ref = self::referencia($a, $i);
            foreach (self::CAMPOS_OBLIGATORIOS as $c) {
                if (!isset($a[$c]) || trim((string) $a[$c]) === '' || ($c === 'items_id' && (int) $a[$c] <= 0)) {
                    $e[] = sprintf('%s: falta %s.', $ref, self::ETIQUETAS[$c]);
                }
            }

            $clave = ($a['itemtype'] ?? '') . '#' . ($a['items_id'] ?? '');
            if (isset($vistos[$clave])) {
                $e[] = sprintf('%s: activo repetido en el acta.', $ref);
            }
            $vistos[$clave] = true;

            // Consistencia inventario-acta según el tipo de movimiento
            $asignado = $a['users_id_asignado'] ?? null;
            $esperado = self::asignadoEsperado($tipo, $entrega, $recibe);
            if ($esperado !== null && $asignado !== $esperado) {
                $e[] = sprintf(
                    '%s: en GLPI no está asignado a %s.',
                    $ref,
                    $tipo === self::TIPO_DEVOLUCION ? 'quien lo devuelve' : 'quien lo recibe'
                );
            }
        }
        return $e;
    }

    /**
     * A quién debe tener asignado GLPI el activo para que el acta sea consistente.
     * - Entrega y traslado: el activo ya debe figurar a nombre de quien lo recibe.
     * - Devolución: el activo debe seguir a nombre de quien lo devuelve.
     */
    public static function asignadoEsperado(string $tipo, int $entrega, int $recibe): ?int
    {
        return match ($tipo) {
            self::TIPO_ENTREGA, self::TIPO_TRASLADO => $recibe,
            self::TIPO_DEVOLUCION                   => $entrega,
            default                                 => null,
        };
    }

    /** Número consecutivo legible por tipo: AE-2026-000123 (entrega), AD- (devolución), AT- (traslado). */
    public static function numero(string $tipo, int $anio, int $consecutivo): string
    {
        $prefijo = match ($tipo) {
            self::TIPO_DEVOLUCION => 'AD',
            self::TIPO_TRASLADO   => 'AT',
            default               => 'AE',
        };
        return sprintf('%s-%04d-%06d', $prefijo, $anio, $consecutivo);
    }

    /**
     * Contenido canónico: solo los campos que identifican el acta, con claves ordenadas,
     * activos ordenados y UTF-8 sin escapes. Dos llamadas con los mismos datos producen
     * exactamente la misma cadena, y por tanto la misma huella.
     */
    public static function canonico(array $d): string
    {
        $version = (int) ($d['version'] ?? self::VERSION_ACTUAL);

        $activos = [];
        foreach ($d['activos'] ?? [] as $a) {
            $fila = [];
            foreach (self::CAMPOS_CANONICOS as $c) {
                $fila[$c] = $c === 'items_id' ? (int) ($a[$c] ?? 0) : trim((string) ($a[$c] ?? ''));
            }
            if ($version >= 2) {
                $fila['detalle'] = self::detalle($a['detalle'] ?? []);
            }
            $activos[] = $fila;
        }
        usort($activos, fn ($x, $y) => [$x['itemtype'], $x['items_id']] <=> [$y['itemtype'], $y['items_id']]);

        $persona = function ($p) use ($version) {
            $out = ['id' => (int) ($p['id'] ?? 0), 'nombre' => trim((string) ($p['nombre'] ?? ''))];
            if ($version >= 2) {
                foreach (self::DATOS_PERSONA as $c) {
                    $out[$c] = trim((string) ($p[$c] ?? ''));
                }
            }
            return $out;
        };

        $doc = [
            'activos'       => $activos,
            'entidad'       => (int) ($d['entidad'] ?? 0),
            'entrega'       => $persona($d['entrega'] ?? []),
            'fecha'         => (string) ($d['fecha'] ?? ''),
            'numero'        => (string) ($d['numero'] ?? ''),
            'observaciones' => trim((string) ($d['observaciones'] ?? '')),
            'recibe'        => $persona($d['recibe'] ?? []),
            'tipo'          => (string) ($d['tipo'] ?? ''),
            'version'       => $version,
        ];
        if ($version >= 2) {
            $doc['accesorios'] = trim((string) ($d['accesorios'] ?? ''));
        }
        return json_encode(self::ordenar($doc), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Normaliza las especificaciones técnicas: acepta un arreglo o el JSON guardado en la base de datos,
     * conserva solo las claves conocidas con valor y devuelve un objeto (nunca una lista).
     *
     * @return array<string,string>|\stdClass
     */
    public static function detalle(array|string|null $detalle): array|\stdClass
    {
        if (is_string($detalle)) {
            $detalle = $detalle === '' ? [] : (json_decode($detalle, true) ?: []);
        }
        $out = [];
        foreach (array_keys(self::DETALLE) as $clave) {
            $v = trim((string) ($detalle[$clave] ?? ''));
            if ($v !== '') {
                $out[$clave] = $v;
            }
        }
        // Un activo sin especificaciones se representa como {} y no como [], para que el JSON sea estable.
        return $out === [] ? new \stdClass() : $out;
    }

    /** Tamaño en MB (como lo guarda GLPI) a texto legible: 16384 → «16 GB», 512000 → «512 GB», 1000000 → «1 TB». */
    public static function tamano(int $mb): string
    {
        if ($mb <= 0) {
            return '';
        }
        if ($mb < 1000) {
            return $mb . ' MB';
        }
        // GLPI no fija la base: los discos suelen registrarse en base 1000 y la memoria en base 1024.
        $gb = $mb % 1000 === 0 ? intdiv($mb, 1000) : (int) round($mb / 1024);
        if ($gb >= 1000 && $gb % 1000 === 0) {
            return intdiv($gb, 1000) . ' TB';
        }
        return $gb . ' GB';
    }

    /** Huella SHA-256 del contenido canónico. Prueba integridad, no autoría: no es una firma digital. */
    public static function huella(array $d): string
    {
        return hash('sha256', self::canonico($d));
    }

    public static function verificar(array $d, string $huella): bool
    {
        return hash_equals(strtolower($huella), self::huella($d));
    }

    /** Porcentaje de campos obligatorios diligenciados (variable «completitud»). */
    public static function completitud(array $d): float
    {
        $total = 0;
        $llenos = 0;
        foreach ($d['activos'] ?? [] as $a) {
            foreach (self::CAMPOS_OBLIGATORIOS as $c) {
                $total++;
                if (isset($a[$c]) && trim((string) $a[$c]) !== '' && !($c === 'items_id' && (int) $a[$c] <= 0)) {
                    $llenos++;
                }
            }
        }
        return $total === 0 ? 0.0 : round(100 * $llenos / $total, 1);
    }

    private static function referencia(array $a, int $i): string
    {
        $nombre = trim((string) ($a['nombre'] ?? ''));
        return $nombre !== '' ? sprintf('Activo «%s»', $nombre) : sprintf('Activo %d', $i + 1);
    }

    private static function ordenar(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }
        if (!array_is_list($v)) {
            ksort($v, SORT_STRING);
        }
        return array_map([self::class, 'ordenar'], $v);
    }
}
