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
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->date('invoice_date')->nullable()->after('invoice_number');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->date('invoice_date')->nullable()->after('invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_and_purchase_invoices', function (Blueprint $table) {
            //
        });
    }
};
