<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['article_id', 'supplier_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            // Bewusst ohne onDelete-Regel: der Lieferant darf nicht endgültig
            // gelöscht werden, solange Bewegungen auf ihn zeigen. Das prüft
            // SupplierManagementController::forceDelete().
            $table->foreignId('supplier_id')->nullable()->after('article_id')->constrained();
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'unit_price']);
        });

        Schema::dropIfExists('article_supplier');
        Schema::dropIfExists('suppliers');
    }
};
