<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// slug = định danh subdomain (vd "mamble" -> mamble.365sport-cms.test) cho trang landing page
// riêng của từng đối tác (Partner::hasFeature('landing_page')) — xem
// BladeThemeV1Controller::partnerLandingPage(). Nullable + unique: đối tác cũ không có slug thì
// không có subdomain, không ảnh hưởng gì tới các đối tác khác.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
