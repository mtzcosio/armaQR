<?php
/**
 * UBICACIÓN DE QUIEN ESCANEA (país, región y ciudad aproximados por IP)
 * --------------------------------------------------------------------
 * Fuentes, en orden:
 *  1. Cloudflare: si el sitio está detrás de Cloudflare, sus encabezados CF-IPCountry y, con la
 *     regla "Add visitor location headers", CF-IPCity y CF-Region.
 *  2. Base local en formato MMDB (DB-IP "IP to City Lite", CC BY 4.0) en database/geoip/ciudades.mmdb.
 *     La descarga y actualiza database/actualizar_geoip.php (una vez al mes). La consulta es local:
 *     la IP no se manda a ningún servicio y tampoco se guarda (qr_escaneos solo tiene su hash).
 * Sin ninguna de las dos, la ubicación queda vacía y el escaneo se registra igual.
 */

declare(strict_types=1);

const GEOIP_ARCHIVO = __DIR__ . '/../database/geoip/ciudades.mmdb';

/** Lector mínimo de bases MaxMind DB (formato MMDB 2.x): árbol de búsqueda + sección de datos. */
final class GeoIpLector
{
    private const MARCADOR = "\xAB\xCD\xEFMaxMind.com";

    /** @var resource */
    private $archivo;
    private int $nodos;
    private int $tamRegistro;
    private int $versionIp;
    private int $inicioDatos;
    private int $nodoIpv4 = 0;
    public array $metadatos;

    public function __construct(string $ruta)
    {
        $archivo = is_file($ruta) ? fopen($ruta, 'rb') : false;
        if ($archivo === false) {
            throw new RuntimeException("No existe la base de ubicaciones: {$ruta}");
        }
        $this->archivo = $archivo;
        $tamano = (int) filesize($ruta);
        $cola = min($tamano, 131072);
        $fin = $this->leer($tamano - $cola, $cola);
        $pos = strrpos($fin, self::MARCADOR);
        if ($pos === false) {
            throw new RuntimeException('El archivo no es una base MMDB válida.');
        }
        $inicioMeta = $tamano - $cola + $pos + strlen(self::MARCADOR);
        [$this->metadatos] = $this->decodificar($inicioMeta, $inicioMeta);
        $this->nodos = (int) ($this->metadatos['node_count'] ?? 0);
        $this->tamRegistro = (int) ($this->metadatos['record_size'] ?? 0);
        $this->versionIp = (int) ($this->metadatos['ip_version'] ?? 0);
        if ($this->nodos <= 0 || !in_array($this->tamRegistro, [24, 28, 32], true) || !in_array($this->versionIp, [4, 6], true)) {
            throw new RuntimeException('Los metadatos de la base MMDB no son válidos.');
        }
        $this->inicioDatos = intdiv($this->tamRegistro * 2, 8) * $this->nodos + 16;
        if ($this->versionIp === 6) {
            // Las IPv4 viven en ::a.b.c.d: 96 bits en cero desde la raíz.
            for ($i = 0; $i < 96 && $this->nodoIpv4 < $this->nodos; $i++) {
                $this->nodoIpv4 = $this->registro($this->nodoIpv4, 0);
            }
        }
    }

    public function __destruct()
    {
        if (is_resource($this->archivo)) {
            fclose($this->archivo);
        }
    }

    /** Datos de la IP (arreglo tal como viene en la base) o null si no aparece. */
    public function buscar(string $ip): ?array
    {
        $binario = @inet_pton($ip);
        if ($binario === false) {
            return null;
        }
        $bits = strlen($binario) * 8;
        if ($bits === 128 && $this->versionIp === 4) {
            return null;
        }
        $nodo = $bits === 32 && $this->versionIp === 6 ? $this->nodoIpv4 : 0;
        for ($i = 0; $i < $bits && $nodo < $this->nodos; $i++) {
            $bit = (ord($binario[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $nodo = $this->registro($nodo, $bit);
        }
        if ($nodo <= $this->nodos) {
            return null; // igual a nodos: sin datos; menor: la IP es más corta que el árbol
        }
        [$valor] = $this->decodificar($this->inicioDatos + ($nodo - $this->nodos) - 16, $this->inicioDatos);
        return is_array($valor) ? $valor : null;
    }

    private function leer(int $pos, int $largo): string
    {
        if ($largo <= 0) {
            return '';
        }
        fseek($this->archivo, $pos);
        $datos = (string) fread($this->archivo, $largo);
        if (strlen($datos) !== $largo) {
            throw new RuntimeException('La base MMDB está incompleta.');
        }
        return $datos;
    }

    /** Entero sin signo big-endian. */
    private static function entero(string $bytes): int|float
    {
        $n = 0;
        foreach (str_split($bytes) as $b) {
            $n = $n * 256 + ord($b);
        }
        return $n;
    }

    /** Registro izquierdo (bit 0) o derecho (bit 1) de un nodo del árbol. */
    private function registro(int $nodo, int $bit): int
    {
        $tamNodo = intdiv($this->tamRegistro * 2, 8);
        $b = $this->leer($nodo * $tamNodo, $tamNodo);
        return match ($this->tamRegistro) {
            24 => (int) self::entero(substr($b, $bit * 3, 3)),
            28 => $bit === 0
                ? ((ord($b[3]) & 0xF0) << 20) | (int) self::entero(substr($b, 0, 3))
                : ((ord($b[3]) & 0x0F) << 24) | (int) self::entero(substr($b, 4, 3)),
            default => (int) self::entero(substr($b, $bit * 4, 4)),
        };
    }

    /** Decodifica el valor en $pos; los punteros son relativos a $base. Devuelve [valor, posición siguiente]. */
    private function decodificar(int $pos, int $base): array
    {
        $control = ord($this->leer($pos++, 1));
        $tipo = $control >> 5;
        if ($tipo === 1) { // puntero
            $tam = ($control >> 3) & 0x3;
            $n = (int) self::entero($this->leer($pos, $tam + 1));
            $pos += $tam + 1;
            $bajos = $control & 0x7;
            $destino = match ($tam) {
                0 => ($bajos << 8) | $n,
                1 => (($bajos << 16) | $n) + 2048,
                2 => (($bajos << 24) | $n) + 526336,
                default => $n,
            };
            [$valor] = $this->decodificar($base + $destino, $base);
            return [$valor, $pos];
        }
        if ($tipo === 0) { // tipo extendido
            $tipo = 7 + ord($this->leer($pos++, 1));
        }
        $tam = $control & 0x1F;
        if ($tam >= 29) {
            $extra = $tam - 28;
            $n = (int) self::entero($this->leer($pos, $extra));
            $pos += $extra;
            $tam = [1 => 29, 2 => 285, 3 => 65821][$extra] + $n;
        }

        switch ($tipo) {
            case 2: // texto UTF-8
            case 4: // bytes
                return [$this->leer($pos, $tam), $pos + $tam];
            case 3: // double
                return [unpack('E', $this->leer($pos, 8))[1], $pos + 8];
            case 15: // float
                return [unpack('G', $this->leer($pos, 4))[1], $pos + 4];
            case 5: case 6: case 9: case 10: // enteros sin signo
                return [self::entero($this->leer($pos, $tam)), $pos + $tam];
            case 8: // int32
                $n = (int) self::entero($this->leer($pos, $tam));
                return [$tam === 4 && $n >= 0x80000000 ? $n - 0x100000000 : $n, $pos + $tam];
            case 7: // mapa
                $mapa = [];
                for ($i = 0; $i < $tam; $i++) {
                    [$clave, $pos] = $this->decodificar($pos, $base);
                    [$mapa[(string) $clave], $pos] = $this->decodificar($pos, $base);
                }
                return [$mapa, $pos];
            case 11: // arreglo
                $lista = [];
                for ($i = 0; $i < $tam; $i++) {
                    [$lista[], $pos] = $this->decodificar($pos, $base);
                }
                return [$lista, $pos];
            case 14: // booleano: el tamaño es el valor
                return [$tam !== 0, $pos];
        }
        throw new RuntimeException("Tipo de dato MMDB no soportado: {$tipo}");
    }
}

/** ¿Hay base de ubicaciones local que se pueda leer? (Sin ella, solo Cloudflare da ubicación.) */
function geoip_disponible(string $archivo = GEOIP_ARCHIVO): bool
{
    return is_file($archivo) && is_readable($archivo) && filesize($archivo) > 1048576;
}

/** Nombre en español si la base lo trae; si no, en inglés. */
function geoip_nombre(?array $nombres): ?string
{
    $nombre = $nombres['es'] ?? $nombres['en'] ?? null;
    return is_string($nombre) && $nombre !== '' ? mb_substr($nombre, 0, 100) : null;
}

/**
 * DB-IP Lite trae las ciudades y estados solo en inglés: corrige los casos comunes en países de
 * habla hispana ("Mexico City" → "Ciudad de México", "San Luis Potosí City" → "San Luis Potosí").
 */
function geoip_espanol(?string $nombre, ?string $pais): ?string
{
    if ($nombre === null) {
        return null;
    }
    $fijos = ['Mexico City' => 'Ciudad de México', 'State of Mexico' => 'Estado de México', 'Mexico' => 'Estado de México',
              'Federal District' => 'Ciudad de México', 'Guatemala City' => 'Ciudad de Guatemala', 'Panama City' => 'Ciudad de Panamá'];
    if (isset($fijos[$nombre]) && in_array($pais, ['MX', 'GT', 'PA'], true)) {
        return $fijos[$nombre];
    }
    $hispanos = ['MX', 'GT', 'SV', 'HN', 'NI', 'CR', 'PA', 'CU', 'DO', 'PR', 'CO', 'VE', 'EC', 'PE', 'BO', 'CL', 'AR', 'UY', 'PY', 'ES'];
    return in_array($pais, $hispanos, true) && str_ends_with($nombre, ' City') ? substr($nombre, 0, -5) : $nombre;
}

/** País, región y ciudad de una IP pública según la base local, o null (sin base, IP privada o sin datos). */
function geoip_buscar(string $ip, string $archivo = GEOIP_ARCHIVO): ?array
{
    static $lectores = [];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || !is_file($archivo)) {
        return null;
    }
    try {
        $lectores[$archivo] ??= new GeoIpLector($archivo);
        $d = $lectores[$archivo]->buscar($ip);
    } catch (Throwable $exception) {
        error_log('GeoIP: ' . $exception->getMessage());
        return null;
    }
    if ($d === null) {
        return null;
    }
    $pais = $d['country']['iso_code'] ?? null;
    $pais = is_string($pais) && preg_match('/^[A-Z]{2}$/', $pais) ? $pais : null;
    return [
        'pais' => $pais,
        'region' => geoip_espanol(geoip_nombre($d['subdivisions'][0]['names'] ?? null), $pais),
        'ciudad' => geoip_espanol(geoip_nombre($d['city']['names'] ?? null), $pais),
    ];
}

/** Ubicación de quien escanea a partir de $_SERVER: ['pais', 'region', 'ciudad'] (cada uno puede ser null). */
function geoip_ubicacion(array $servidor, string $archivo = GEOIP_ARCHIVO): array
{
    $vacio = ['pais' => null, 'region' => null, 'ciudad' => null];
    $cfPais = strtoupper((string) ($servidor['HTTP_CF_IPCOUNTRY'] ?? ''));
    if ($cfPais !== '') {
        // Detrás de Cloudflare, REMOTE_ADDR es de Cloudflare: la IP real va en CF-Connecting-IP.
        $texto = fn(string $clave) => ($v = trim((string) ($servidor[$clave] ?? ''))) !== '' ? mb_substr($v, 0, 100) : null;
        $ubicacion = ['pais' => preg_match('/^[A-Z]{2}$/', $cfPais) && !in_array($cfPais, ['XX', 'T1'], true) ? $cfPais : null,
                      'region' => $texto('HTTP_CF_REGION'), 'ciudad' => $texto('HTTP_CF_IPCITY')];
        if ($ubicacion['ciudad'] === null) {
            $local = geoip_buscar((string) ($servidor['HTTP_CF_CONNECTING_IP'] ?? ''), $archivo);
            if ($local !== null && ($ubicacion['pais'] === null || $local['pais'] === $ubicacion['pais'])) {
                $ubicacion = ['pais' => $local['pais'], 'region' => $local['region'], 'ciudad' => $local['ciudad']];
            }
        }
        return $ubicacion;
    }
    return geoip_buscar((string) ($servidor['REMOTE_ADDR'] ?? ''), $archivo) ?? $vacio;
}

/** Nombre del país en español a partir de su código ISO (con intl si está; si no, los más comunes). */
function geoip_pais_nombre(string $codigo): string
{
    if (function_exists('locale_get_display_region')) {
        $nombre = locale_get_display_region('-' . $codigo, 'es');
        if ($nombre !== '' && $nombre !== $codigo) {
            return $nombre;
        }
    }
    return [
        'MX' => 'México', 'US' => 'Estados Unidos', 'CA' => 'Canadá', 'GT' => 'Guatemala', 'SV' => 'El Salvador',
        'HN' => 'Honduras', 'NI' => 'Nicaragua', 'CR' => 'Costa Rica', 'PA' => 'Panamá', 'CU' => 'Cuba',
        'DO' => 'República Dominicana', 'PR' => 'Puerto Rico', 'CO' => 'Colombia', 'VE' => 'Venezuela', 'EC' => 'Ecuador',
        'PE' => 'Perú', 'BO' => 'Bolivia', 'CL' => 'Chile', 'AR' => 'Argentina', 'UY' => 'Uruguay', 'PY' => 'Paraguay',
        'BR' => 'Brasil', 'ES' => 'España', 'FR' => 'Francia', 'DE' => 'Alemania', 'IT' => 'Italia', 'GB' => 'Reino Unido',
        'PT' => 'Portugal', 'NL' => 'Países Bajos', 'CN' => 'China', 'JP' => 'Japón', 'KR' => 'Corea del Sur', 'IN' => 'India',
    ][$codigo] ?? $codigo;
}

/** Bandera (emoji) de un código de país ISO. */
function geoip_bandera(string $codigo): string
{
    if (!preg_match('/^[A-Z]{2}$/', $codigo)) {
        return '';
    }
    return mb_chr(0x1F1E6 + ord($codigo[0]) - 65) . mb_chr(0x1F1E6 + ord($codigo[1]) - 65);
}
