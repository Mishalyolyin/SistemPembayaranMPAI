<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Hash;

class ImportMahasiswaCSV extends Command
{
    protected $signature = 'import:mahasiswa {path}';
    protected $description = 'Import data mahasiswa dari file CSV';

    public function handle()
        {
            $path = $this->argument('path'); // contoh: data/mahasiswa.csv
            $fullPath = storage_path('app/' . $path);

            $this->info("🔍 Mencoba mengakses file di path:");
            $this->line($fullPath); // tampilkan lokasi penuh

            if (!file_exists($fullPath)) {
                $this->error("❌ File tidak ditemukan di lokasi tersebut!");
                return;
            }

            $this->info("✅ File ditemukan! Kamu siap melakukan proses import...");
            
            // ------ lanjut import CSV kalau file sudah ketemu ------
            $csv = array_map('str_getcsv', file($fullPath));
            $header = array_map('strtolower', $csv[0]);
            unset($csv[0]);

            foreach ($csv as $row) {
                $row = array_combine($header, $row);
                \App\Models\Mahasiswa::updateOrCreate(
                    ['nim' => $row['nim']],
                    [
                        'nama' => $row['nama'],
                        'email' => null,
                        'no_hp' => null,
                        'alamat' => null,
                        'status' => 'Aktif',
                        'password' => \Illuminate\Support\Facades\Hash::make('123456')
                    ]
                );
            }

            $this->info("🎉 Import selesai. Total: " . count($csv) . " baris.");
        }

}
