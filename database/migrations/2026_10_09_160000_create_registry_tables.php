<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry schema. Only portable column types are used so the same
 * migrations run unchanged on SQLite, MySQL/MariaDB and PostgreSQL.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tlds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 63)->unique();
            $table->string('unicode_name', 63)->nullable();
            $table->unsignedInteger('default_ttl')->default(3600);
            $table->string('soa_mname');
            $table->string('soa_rname');
            $table->unsignedInteger('soa_refresh')->default(7200);
            $table->unsignedInteger('soa_retry')->default(3600);
            $table->unsignedInteger('soa_expire')->default(1209600);
            $table->unsignedInteger('soa_minimum')->default(3600);
            $table->unsignedBigInteger('serial')->default(0);
            $table->json('apex_nameservers')->nullable();
            $table->timestamps();
        });

        Schema::create('registrars', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 64)->unique();
            $table->unsignedInteger('iana_id')->nullable()->unique();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('url')->nullable();
            $table->string('abuse_email')->nullable();
            $table->string('abuse_phone', 32)->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->foreignId('registrar_id')->nullable()->after('is_admin')
                ->constrained()->nullOnDelete();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 64)->unique();
            $table->string('kind', 16)->default('individual');
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2);
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('fax', 32)->nullable();
            $table->json('disclose')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tld_id')->constrained()->restrictOnDelete();
            $table->string('name', 253)->unique();
            $table->string('unicode_name', 253)->nullable();
            $table->foreignId('registrant_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('registrar_id')->nullable()->constrained()->nullOnDelete();
            $table->json('statuses')->nullable();
            $table->text('auth_code')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('registrar_id');
            $table->index('expires_at');
        });

        Schema::create('contact_domain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['domain_id', 'contact_id', 'role']);
        });

        Schema::create('nameservers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 253)->unique();
            $table->string('unicode_name', 253)->nullable();
            $table->timestamps();
        });

        Schema::create('nameserver_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nameserver_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->unsignedTinyInteger('version');
            $table->timestamps();

            $table->unique(['nameserver_id', 'ip']);
        });

        Schema::create('domain_nameserver', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nameserver_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['domain_id', 'nameserver_id']);
        });

        Schema::create('ds_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('key_tag');
            $table->unsignedTinyInteger('algorithm');
            $table->unsignedTinyInteger('digest_type');
            $table->string('digest', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ds_records');
        Schema::dropIfExists('domain_nameserver');
        Schema::dropIfExists('nameserver_addresses');
        Schema::dropIfExists('nameservers');
        Schema::dropIfExists('contact_domain');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('contacts');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrar_id');
            $table->dropColumn('is_admin');
        });

        Schema::dropIfExists('registrars');
        Schema::dropIfExists('tlds');
    }
};
