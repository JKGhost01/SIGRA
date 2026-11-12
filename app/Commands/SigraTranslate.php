<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\TranslationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Translator;

/**
 * Genera o completa un archivo de idioma a partir del español.
 *
 *   php spark sigra:translate
 *   php spark sigra:translate --locale en --provider mymemory
 *   php spark sigra:translate --force
 *   php spark sigra:translate --key navMap
 *
 * El español es la fuente de verdad. Añade una clave nueva en
 * app/Language/es/Sigra.php, usa lang('Sigra.clave') en la vista y
 * ejecuta este comando: el resto de idiomas se rellena solo.
 */
class SigraTranslate extends BaseCommand
{
    protected $group       = 'SIGRA';
    protected $name        = 'sigra:translate';
    protected $description = 'Traduce automáticamente las cadenas de interfaz que falten.';
    protected $usage       = 'sigra:translate [--locale en] [--provider google] [--force] [--key navMap] [--dry-run]';
    protected $options     = [
        '--locale'   => 'Idioma destino (por defecto en).',
        '--provider' => 'google, mymemory, libretranslate o deepl.',
        '--force'    => 'Vuelve a traducir todas las claves, no solo las que faltan.',
        '--key'      => 'Traduce únicamente una clave.',
        '--dry-run'  => 'Muestra lo que haría sin escribir el archivo.',
    ];

    public function run(array $params): void
    {
        $locale = strtolower((string) (CLI::getOption('locale') ?: 'en'));
        if (! preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $locale)) {
            CLI::error('Locale inválido: ' . $locale);

            return;
        }

        $config = config(Translator::class);
        $sourceLocale = $config->sourceLocale;
        if ($locale === $sourceLocale) {
            CLI::error('El locale destino debe ser distinto de ' . $sourceLocale);

            return;
        }

        $sourceFile = APPPATH . 'Language/' . $sourceLocale . '/Sigra.php';
        $targetFile = APPPATH . 'Language/' . $locale . '/Sigra.php';
        if (! is_file($sourceFile)) {
            CLI::error('No existe el archivo fuente: ' . $sourceFile);

            return;
        }

        /** @var array<string, string> $source */
        $source = require $sourceFile;
        /** @var array<string, string> $target */
        $target = is_file($targetFile) ? require $targetFile : [];

        $service = new TranslationService($config);
        $provider = (string) CLI::getOption('provider');
        if ($provider !== '') {
            $service->setProvider($provider);
        }

        $force  = (bool) CLI::getOption('force');
        $dryRun = (bool) CLI::getOption('dry-run');
        $only   = trim((string) CLI::getOption('key'));

        $ordered = [];
        $translatedCount = 0;
        $pending = 0;
        $failed = 0;

        foreach ($source as $key => $value) {
            if ($only !== '' && $key !== $only) {
                if (isset($target[$key])) {
                    $ordered[$key] = $target[$key];
                }

                continue;
            }

            $existing = trim((string) ($target[$key] ?? ''));
            if (! $force && $existing !== '') {
                $ordered[$key] = $target[$key];

                continue;
            }

            if ($dryRun) {
                CLI::write('[dry-run] ' . $key . ' → ' . $value, 'yellow');
                $pending++;
                $ordered[$key] = $existing !== '' ? $target[$key] : $value;

                continue;
            }

            $translated = $service->translate((string) $value, $locale);
            if ($translated === null) {
                CLI::error('No se pudo traducir: ' . $key);
                $failed++;
                $ordered[$key] = $existing !== '' ? $target[$key] : $value;

                continue;
            }

            $ordered[$key] = $translated;
            $translatedCount++;
            CLI::write('✓ ' . $key . ' → ' . $translated, 'green');
        }

        foreach ($target as $key => $value) {
            if (! array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        if ($dryRun) {
            CLI::write('Dry run: ' . count($source) . ' claves, ' . $pending . ' pendientes.', 'yellow');

            return;
        }

        $directory = dirname($targetFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $content = "<?php\n\n"
            . "// Generado por `php spark sigra:translate`.\n"
            . "// El español es la fuente de verdad; las ediciones manuales se conservan\n"
            . "// salvo que ejecutes el comando con --force.\n\n"
            . 'return ' . var_export($ordered, true) . ";\n";

        file_put_contents($targetFile, $content, LOCK_EX);

        CLI::newLine();
        CLI::write('Archivo escrito: ' . $targetFile, 'green');
        CLI::write('Nuevas traducciones: ' . $translatedCount, 'green');
        if ($failed > 0) {
            CLI::error('Fallidas: ' . $failed);
        }
        CLI::write('Revisa el resultado antes de publicar; la traducción automática no sustituye una revisión humana.', 'yellow');
    }
}
