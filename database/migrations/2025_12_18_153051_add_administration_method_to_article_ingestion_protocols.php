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
            $table->string('administration_method')->nullable()->after('species_id');
        });
    }

    public function down()
    {
        Schema::table('article_ingestion_protocols', function (Blueprint $table) {
            $table->dropColumn('administration_method');
        });
    }
};
