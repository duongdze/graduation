<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'lock_reason')) {
                $table->text('lock_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('users', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('lock_reason');
            }

            if (! Schema::hasColumn('users', 'locked_by')) {
                $table->uuid('locked_by')->nullable()->after('locked_at');
                $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('venue_clusters', function (Blueprint $table) {
            if (! Schema::hasColumn('venue_clusters', 'lock_reason')) {
                $table->text('lock_reason')->nullable()->after('reject_reason');
            }

            if (! Schema::hasColumn('venue_clusters', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('lock_reason');
            }

            if (! Schema::hasColumn('venue_clusters', 'locked_by')) {
                $table->uuid('locked_by')->nullable()->after('locked_at');
                $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::create('moderation_configs', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value');
            $table->string('value_type', 20)->default('string');
            $table->text('description')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('system_policies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('key', 100)->unique();
            $table->string('title');
            $table->longText('content');
            $table->string('type', 50)->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['type', 'is_active']);
            $table->index('effective_from');
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('title');
            $table->string('image_url', 1000);
            $table->string('link_url', 1000)->nullable();
            $table->string('position', 50)->default('home');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['position', 'is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('system_posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('thumbnail', 1000)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('venue_cluster_id')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('published');
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index('author_id');
            $table->index('venue_cluster_id');
        });

        Schema::create('community_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('community_post_comments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->longText('content');
            $table->uuid('parent_id')->nullable();
            $table->string('status', 20)->default('visible');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('community_post_comments')->cascadeOnDelete();
            $table->index(['post_id', 'status', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('favorite_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('venue_cluster_id');
            $table->timestamp('created_at')->nullable();

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
            $table->unique(['user_id', 'venue_cluster_id']);
            $table->index('venue_cluster_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_venues');
        Schema::dropIfExists('community_post_comments');
        Schema::dropIfExists('community_post_likes');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('system_posts');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('system_policies');
        Schema::dropIfExists('moderation_configs');

        Schema::table('venue_clusters', function (Blueprint $table) {
            if (Schema::hasColumn('venue_clusters', 'locked_by')) {
                $table->dropForeign(['locked_by']);
                $table->dropColumn('locked_by');
            }

            if (Schema::hasColumn('venue_clusters', 'locked_at')) {
                $table->dropColumn('locked_at');
            }

            if (Schema::hasColumn('venue_clusters', 'lock_reason')) {
                $table->dropColumn('lock_reason');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'locked_by')) {
                $table->dropForeign(['locked_by']);
                $table->dropColumn('locked_by');
            }

            if (Schema::hasColumn('users', 'locked_at')) {
                $table->dropColumn('locked_at');
            }

            if (Schema::hasColumn('users', 'lock_reason')) {
                $table->dropColumn('lock_reason');
            }
        });
    }
};
