<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNofollowTable extends Migration
{

    /**
     * This variable is the table`s name
     *
     * @var string $name_table
     */
    protected string $name_table = 'nofollow';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable($this->name_table)) {
            Schema::create($this->name_table, function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(1)->comment('Вкл./Откл. домен на закрытие');
                $table->boolean('follow')->default(1)->comment('Закрыть/Не закрывать от индексации');
                $table->string('domain')->index()->unique()->comment('Имя домена (без протокола и слеша в конце)');
            });
            Illuminate\Support\Facades\DB::statement("ALTER TABLE `" . env('DB_PREFIX') . $this->name_table ."` comment 'Таблица закрытие ссылок от индексации'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->name_table);
    }
}
