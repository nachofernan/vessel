<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('elements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // fire, water, earth, air, light, shadow, anima
            $table->string('color');
            $table->timestamps();
        });

        Schema::create('element_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_element_id')->constrained('elements');
            $table->foreignId('target_element_id')->constrained('elements');
            $table->decimal('multiplier', 4, 2); // 1.50 / 1.00 / 0.60 / 1.25
            $table->timestamps();
        });

        Schema::create('heroes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedInteger('fuerza')->default(5);
            $table->unsignedInteger('resistencia')->default(5);
            $table->unsignedInteger('destreza')->default(5);
            $table->unsignedInteger('inteligencia')->default(5);
            $table->unsignedInteger('suerte')->default(5);
            $table->unsignedInteger('hp_actual')->default(0); // calculado al crear
            $table->unsignedInteger('hp_maximo')->default(0);
            $table->unsignedInteger('oro')->default(0);
            $table->timestamps();
        });

        Schema::create('talismans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->unique()->constrained()->cascadeOnDelete();
 
            // Una columna por elemento, tope 100 (se aplica en código, no en DB)
            $table->unsignedTinyInteger('esencia_fire')->default(0);
            $table->unsignedTinyInteger('esencia_water')->default(0);
            $table->unsignedTinyInteger('esencia_earth')->default(0);
            $table->unsignedTinyInteger('esencia_air')->default(0);
            $table->unsignedTinyInteger('esencia_light')->default(0);
            $table->unsignedTinyInteger('esencia_shadow')->default(0);
            $table->unsignedTinyInteger('esencia_anima')->default(0);
 
            $table->timestamps();
        });

        Schema::create('expeditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained()->cascadeOnDelete();
            $table->string('zone_slug')->default('fire_1'); // hardcodeado en v1
            $table->string('kingdom_slug')->default('fire');
            $table->foreignId('element_id')->constrained(); // elemento de la zona
            $table->unsignedInteger('duration_seconds');
            $table->enum('status', ['running', 'completed', 'finished'])->default('running');
            $table->enum('event_type', ['combat', 'chest', 'merchant', 'silence', 'rest'])->nullable();
            $table->json('resultado')->nullable(); // log completo
            $table->unsignedInteger('carga_obtenida')->default(0);
            $table->unsignedInteger('oro_obtenido')->default(0);
            $table->boolean('hero_died')->default(false);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completes_at')->nullable(); // cuándo termina el timer
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('combat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expedition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('hero_damage_dealt')->default(0);
            $table->unsignedInteger('hero_damage_received')->default(0);
            $table->boolean('hero_dodged')->default(false);
            $table->boolean('hero_double_hit')->default(false);
            $table->boolean('hero_critical')->default(false);
            $table->boolean('enemy_fled')->default(false);
            $table->string('narrative_line');
            $table->timestamps();
        });

        // Catálogo de piezas de equipo
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->enum('piece_type', ['casco', 'pecho', 'brazos', 'piernas', 'escudo', 'arma', 'amuleto']);
            $table->foreignId('element_id')->constrained('elements');
            $table->unsignedTinyInteger('level')->default(1); // 1, 2, 3
            $table->string('name');
            $table->unsignedInteger('stat_bonus');      // bonus al stat asociado a la pieza
            $table->unsignedInteger('alignment_bonus'); // bonus de alineación elemental
            $table->unsignedInteger('carga_maxima')->default(100);
            $table->unsignedInteger('valor_base');      // precio base en oro
            $table->timestamps();
        });
 
        // Qué pieza tiene equipada el héroe en cada slot (máx 7 filas por héroe)
        Schema::create('hero_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained()->cascadeOnDelete();
            $table->enum('piece_type', ['casco', 'pecho', 'brazos', 'piernas', 'escudo', 'arma', 'amuleto']);
            $table->foreignId('equipment_id')->constrained('equipments');
            $table->unsignedInteger('carga')->default(0);
            $table->unique(['hero_id', 'piece_type']); // un slot por tipo
            $table->timestamps();
        });
 
        // Inventario (piezas no equipadas)
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments');
            $table->unsignedInteger('carga')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('combat_logs');
        Schema::dropIfExists('expeditions');
        Schema::dropIfExists('talismans');
        Schema::dropIfExists('heroes');
        Schema::dropIfExists('element_relations');
        Schema::dropIfExists('elements');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('hero_equipment');
        Schema::dropIfExists('equipments');
        Schema::enableForeignKeyConstraints();
    }
};
