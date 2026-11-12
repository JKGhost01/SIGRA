<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración del traductor automático de cadenas.
 *
 * Las vistas se escriben una sola vez con claves (`lang('Sigra.*')`).
 * `php spark sigra:translate` rellena el archivo del idioma destino
 * usando el proveedor configurado aquí.
 *
 *   translator.provider = google|mymemory|libretranslate|deepl
 *   translator.apiKey   = ...
 *   translator.endpoint = ...
 */
class Translator extends BaseConfig
{
    /** google (sin clave), mymemory (sin clave), libretranslate o deepl. */
    public string $provider = 'google';

    /** Endpoint para libretranslate/deepl. Vacío usa el valor por defecto. */
    public string $endpoint = '';

    /** Clave de API para libretranslate/deepl. */
    public string $apiKey = '';

    /** Idioma de origen. */
    public string $sourceLocale = 'es';

    /** Milisegundos entre peticiones no cacheadas. */
    public int $rateLimitMs = 120;

    /** Timeout de la petición HTTP en segundos. */
    public int $timeout = 30;

    /** Directorio de caché de traducciones. */
    public string $cacheDirectory = WRITEPATH . 'cache/translations';

    public function __construct()
    {
        parent::__construct();

        $this->provider     = (string) env('translator.provider', $this->provider);
        $this->endpoint     = (string) env('translator.endpoint', $this->endpoint);
        $this->apiKey       = (string) env('translator.apiKey', $this->apiKey);
        $this->sourceLocale = (string) env('translator.sourceLocale', $this->sourceLocale);
        $this->rateLimitMs  = (int) env('translator.rateLimitMs', $this->rateLimitMs);
        $this->timeout      = (int) env('translator.timeout', $this->timeout);
        $this->cacheDirectory = (string) env('translator.cacheDirectory', $this->cacheDirectory);
    }
}
