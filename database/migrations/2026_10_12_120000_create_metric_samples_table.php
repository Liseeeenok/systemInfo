<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_samples', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sampled_at')->index();

            $table->float('cpu_percent')->default(0);
            $table->jsonb('cpu_per_core')->nullable();
            $table->unsignedSmallInteger('cpu_count')->default(1);
            $table->float('load1')->default(0);
            $table->float('load5')->default(0);
            $table->float('load15')->default(0);

            $table->unsignedBigInteger('mem_total')->default(0);
            $table->unsignedBigInteger('mem_used')->default(0);
            $table->unsignedBigInteger('mem_available')->default(0);
            $table->unsignedBigInteger('mem_cached')->default(0);
            $table->unsignedBigInteger('swap_total')->default(0);
            $table->unsignedBigInteger('swap_used')->default(0);

            $table->unsignedBigInteger('net_rx_rate')->default(0);
            $table->unsignedBigInteger('net_tx_rate')->default(0);
            $table->jsonb('net_ifaces')->nullable();

            $table->unsignedBigInteger('disk_total')->default(0);
            $table->unsignedBigInteger('disk_used')->default(0);
            $table->jsonb('disks')->nullable();

            $table->jsonb('temps')->nullable();
            $table->float('temp_cpu')->nullable();
            $table->float('temp_gpu')->nullable();

            $table->unsignedBigInteger('uptime')->default(0);
            $table->unsignedInteger('procs')->default(0);

            $table->jsonb('top_cpu')->nullable();
            $table->jsonb('top_mem')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_samples');
    }
};
