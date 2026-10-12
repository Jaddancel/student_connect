<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class UserSeeder extends Seeder
{
    public static function assertSignatureRuntime(): void
    {
        $process = new Process([
            config('seeding.python'),
            '-c',
            'from PIL import Image, ImageDraw; assert Image.registered_extensions().get(".jpg") == "JPEG", "Pillow JPEG support is required"',
        ]);
        $process->setTimeout(30);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Signature generation requires Python with Pillow and JPEG support. '
                .'Install database/seeders/scripts/requirements.txt in a virtualenv and set SEED_PYTHON to its Python executable. '
                .'Python error: '.trim($process->getErrorOutput().' '.$process->getOutput())
            );
        }
    }

    public function run(): void
    {
        self::assertSignatureRuntime();
        $profiles = User::query()->with('profile')->get()->map(function (User $user) {
            $profile = $user->getRelation('profile');
            if ($profile === null || ! $profile->signature_path || ! $profile->photo) {
                throw new RuntimeException('Every seeded account must have a profile, portrait and signature path.');
            }

            return [
                'id' => (int) $profile->getKey(),
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'path' => Storage::disk('public')->path($profile->signature_path),
            ];
        })->all();

        $path = tempnam(sys_get_temp_dir(), 'seed-signatures-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the signature batch file.');
        }
        try {
            if (file_put_contents($path, json_encode($profiles, JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Unable to write the signature batch file.');
            }
            $process = new Process([
                config('seeding.python'),
                database_path('seeders/scripts/generate_signatures.py'),
                $path,
            ]);
            $process->setTimeout(300);
            $process->mustRun();
        } finally {
            unlink($path);
        }

        $hashes = [];
        foreach ($profiles as $profile) {
            $size = getimagesize($profile['path']);
            if ($size === false || $size[0] !== 100 || $size[1] !== 100 || $size[2] !== IMAGETYPE_JPEG) {
                throw new RuntimeException('Seed signatures must be 100x100 JPEGs.');
            }
            $hash = sha1_file($profile['path']);
            if (isset($hashes[$hash])) {
                throw new RuntimeException('Duplicate seed signatures were generated.');
            }
            $hashes[$hash] = true;
        }
    }
}
