<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFulltextIndexCountriesLanguagesBibles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('dbp')
            ->statement('ALTER TABLE countries ADD FULLTEXT ft_index_countries_name_iso_a3(name, iso_a3)');
        DB::connection('dbp')
            ->statement('ALTER TABLE languages ADD FULLTEXT ft_index_languages_name(name)');
        DB::connection('dbp')
            ->statement('ALTER TABLE language_translations ADD FULLTEXT ft_index_language_translations_name(name)');
        DB::connection('dbp')
            ->statement('ALTER TABLE bible_translations ADD FULLTEXT ft_index_bible_translations_name(name)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('dbp')
            ->statement('ALTER TABLE countries DROP INDEX ft_index_countries_name_iso_a3');
        DB::connection('dbp')
            ->statement('ALTER TABLE languages DROP INDEX ft_index_languages_name');
        DB::connection('dbp')
            ->statement('ALTER TABLE language_translations DROP INDEX ft_index_language_translations_name');
        DB::connection('dbp')
            ->statement('ALTER TABLE bible_translations DROP INDEX ft_index_bible_translations_name');
    }
}
