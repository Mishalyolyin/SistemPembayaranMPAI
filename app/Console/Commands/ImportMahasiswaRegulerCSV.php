<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MahasiswaReguler;
use Illuminate\Support\Facades\Hash;

class ImportMahasiswaRegulerCSV extends Command
{
    protected $signature = 'import:mahasiswa-reguler {path}';
    protected $description = 'Import data mahasiswa reguler dari file CSV';

    public function handle()
    {
        $path = $this->argument('path'); // contoh: data/mahasiswa-reguler.csv
        $fullPath = storage_path('app/' . $path);

        $this->info("🔍 Mencoba mengakses file di path:");
        $this->line($fullPath);

        if (!file_exists($fullPath)) {
            $this->error("❌ File tidak ditemukan di lokasi tersebut!");
            return;
        }

        $this->info("✅ File ditemukan! Proses import dimulai...");

        $csv = array_map('str_getcsv', file($fullPath));
        $header = array_map('strtolower', $csv[0]);
        unset($csv[0]);

        foreach ($csv as $row) {
            $row = array_combine($header, $row);
            MahasiswaReguler::updateOrCreate(
                ['nim' => $row['nim']],
                [
                    'nama' => $row['nama'],
                    'email' => null,
                    'no_hp' => null,
                    'alamat' => null,
                    'status' => 'Aktif',
                    'password' => Hash::make('123456'),
                ]
            );
        }

        $this->info("🎉 Import selesai. Total: " . count($csv) . " baris.");
    }
}
