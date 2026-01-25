<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::table('article_ingestion_protocols', function (Blueprint $table) {
            $table->string('peak_breath_hydrogen_value')->nullable()->after('frequency');
            $table->string('peak_breath_hydrogen_unit')->default('ppm')->after('peak_breath_hydrogen_value');
        });
    }

    public function down()
    {
        Schema::table('article_ingestion_protocols', function (Blueprint $table) {
            $table->dropColumn(['peak_breath_hydrogen_value', 'peak_breath_hydrogen_unit']);
        });
    }
};
