<?php

namespace Database\Seeders;

use App\Services\ConfigBackup\ConfigImporter;
use App\Services\Scoring\ScoringCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $config = json_decode(file_get_contents(__DIR__.'/data/config.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (['forms', 'report_templates'] as $section) {
            foreach ($config[$section] as &$item) {
                $item['templates'] = [];
            }
            unset($item);
        }

        $path = tempnam(sys_get_temp_dir(), 'seed-config-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the seed configuration archive.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the seed configuration archive.');
            }
            $zip->addFromString('manifest.json', file_get_contents(__DIR__.'/data/manifest.json'));
            $zip->addFromString('config.json', json_encode($config, JSON_THROW_ON_ERROR));
            $zip->close();

            $result = app(ConfigImporter::class)->import($path);
            foreach ($result['warnings'] as $warning) {
                Log::warning('Seed configuration: '.$warning);
                $this->command?->warn($warning);
            }
            ScoringCatalog::flush();
        } finally {
            unlink($path);
        }
    }
}
