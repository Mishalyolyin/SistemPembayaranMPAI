<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKalenderEventsTable extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_events', function (Blueprint $table) {
            $table->id();
            $table->string('judul_event');
            $table->date('tanggal');
            $table->enum('untuk', ['rpl', 'reguler', 'all'])->default('all');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_events');
    }
}
