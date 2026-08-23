<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class ConvertOpenapiToJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scribe:openapi-to-json
                            {--input=public/docs/openapi.yaml : Path to the source OpenAPI YAML file}
                            {--output=public/docs/openapi.json : Path to write the JSON output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert the Scribe-generated openapi.yaml to openapi.json for clients that prefer JSON';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $inputPath  = base_path($this->option('input'));
        $outputPath = base_path($this->option('output'));

        if (! file_exists($inputPath)) {
            $this->error("Source file not found: {$inputPath}");
            $this->line('Run `php artisan scribe:generate` first.');

            return self::FAILURE;
        }

        $this->info("Reading {$inputPath} ...");

        $yaml = Yaml::parseFile($inputPath);
        $json = json_encode($yaml, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $this->error('Failed to encode YAML to JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $json);

        $this->info("Written to {$outputPath}");

        return self::SUCCESS;
    }
}
