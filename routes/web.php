<?php
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;
use Illuminate\Database\Eloquent\Model;

Route::get('/', function () {
    return null;
});
Route::get('/list-migrations', function () {
    $migrations = [];
    $migrationsPath = database_path('migrations');

    $finder = new Finder();
    $finder->in($migrationsPath)->files()->name('*.php');

    foreach ($finder as $file) {
        $content = $file->getContents();

        // Find all schema creation blocks
        preg_match_all('/Schema::create\(.*?}\);\n?/s', $content, $schemaMatches);
        $schemas = $schemaMatches[0] ?? [];

        if (!empty($schemas)) {
            $migrations[] = [
                'filename' => $file->getFilename(),
                'schemas' => array_map('trim', $schemas)
            ];
        }
    }

    $output = '';
    foreach ($migrations as $migration) {
        $output .= "";
        foreach ($migration['schemas'] as $schema) {
            $output .= $schema . "\n";
        }
        // $output .= str_repeat('-', 40) . "\n";
    }
    return "<pre>" . ($output ?: "No migrations found") . "</pre>";
});


Route::get('/list-models', function () {
    $migrations = [];
    $migrationsPath = database_path('migrations');

    $finder = new Finder();
    $finder->in($migrationsPath)->files()->name('*.php');

    foreach ($finder as $file) {
        $content = $file->getContents();

        // Find all schema creation blocks
        preg_match_all('/Schema::create\(.*?}\);\n?/s', $content, $schemaMatches);
        $schemas = $schemaMatches[0] ?? [];

        if (!empty($schemas)) {
            $migrations[] = [
                'filename' => $file->getFilename(),
                'schemas' => array_map('trim', $schemas)
            ];
        }
    }

    $output = '';
    foreach ($migrations as $migration) {
        $output .= "";
        foreach ($migration['schemas'] as $schema) {
            $output .= $schema . "\n";
        }
        // $output .= str_repeat('-', 40) . "\n";
    }
    return "<pre>" . ($output ?: "No migrations found") . "</pre>";
});
Route::get('/list-models', function () {
    $modelsContent = [];
    $modelsPath = app_path('Models');

    $finder = new Finder();
    $finder->in($modelsPath)->files()->name('*.php');

    foreach ($finder as $file) {
        $content = $file->getContents();

        // Extract class declaration and body
        if (preg_match('/class\s+.*/s', $content, $matches)) {
            $classContent = $matches[0];

            // Verify the class is an Eloquent model
            $relativePath = $file->getRelativePathname();
            $className = str_replace(['.php', '/'], ['', '\\'], $relativePath);
            $fullClassName = 'App\\Models\\' . $className;

            if (class_exists($fullClassName) && is_subclass_of($fullClassName, Model::class)) {
                $modelsContent[] = $classContent;
            }
        }
    }

    $output = implode("\n\n", $modelsContent);

    return "<pre>" . ($output ?: "No models found") . "</pre>";
});
