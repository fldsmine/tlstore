<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'monthly_share_minutes')) {
                $table->decimal('monthly_share_minutes', 12, 2)
                    ->default(0)
                    ->after('daily_call_minutes');
            }

            if (! Schema::hasColumn('packages', 'share_term_days')) {
                $table->unsignedSmallInteger('share_term_days')
                    ->default(30)
                    ->after('monthly_share_minutes');
            }
        });

        Schema::table('call_types', function (Blueprint $table) {
            if (! Schema::hasColumn('call_types', 'price')) {
                $table->decimal('price', 36, 6)
                    ->default(0)
                    ->after('claimable_percentage');
            }

            if (! Schema::hasColumn('call_types', 'agent_price')) {
                $table->decimal('agent_price', 36, 6)
                    ->default(0)
                    ->after('price');
            }

            if (! Schema::hasColumn('call_types', 'foreign_price')) {
                $table->decimal('foreign_price', 36, 6)
                    ->nullable()
                    ->after('agent_price');
            }

            if (! Schema::hasColumn('call_types', 'foreign_agent_price')) {
                $table->decimal('foreign_agent_price', 36, 6)
                    ->nullable()
                    ->after('foreign_price');
            }

            if (! Schema::hasColumn('call_types', 'region')) {
                $table->string('region')
                    ->default('nigeria')
                    ->after('foreign_agent_price');
            }

            if (! Schema::hasColumn('call_types', 'monthly_share_minutes')) {
                $table->decimal('monthly_share_minutes', 12, 2)
                    ->default(0)
                    ->after('region');
            }

            if (! Schema::hasColumn('call_types', 'share_term_days')) {
                $table->unsignedSmallInteger('share_term_days')
                    ->default(30)
                    ->after('monthly_share_minutes');
            }

            if (! Schema::hasColumn('call_types', 'max_daily_shareholder_earning')) {
                $table->decimal('max_daily_shareholder_earning', 36, 6)
                    ->default(0)
                    ->after('share_term_days');
            }

            if (! Schema::hasColumn('call_types', 'share_purchase_prompt_minutes')) {
                $table->decimal('share_purchase_prompt_minutes', 10, 2)
                    ->default(90)
                    ->after('max_daily_shareholder_earning');
            }
        });

        Schema::table('additional_minute_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('additional_minute_plans', 'share_minutes')) {
                $table->decimal('share_minutes', 12, 2)
                    ->default(0)
                    ->after('daily_call_minutes');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'legacy_shareholder_balance')) {
                $table->decimal('legacy_shareholder_balance', 36, 6)
                    ->default(0)
                    ->after('shareholder_balance');
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'call_type_id')) {
                $table->foreignUuid('call_type_id')
                    ->nullable()
                    ->after('package_id')
                    ->constrained('call_types')
                    ->nullOnDelete();
            }
        });

        Schema::table('coupon_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('coupon_batches', 'call_type_id')) {
                $table->foreignUuid('call_type_id')
                    ->nullable()
                    ->after('package_id')
                    ->constrained('call_types')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('share_subscriptions')) {
            Schema::create('share_subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->foreignUuid('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignUuid('package_id')
                    ->nullable()
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignUuid('call_type_id')
                    ->nullable()
                    ->constrained('call_types')
                    ->restrictOnDelete();

                $table->string('region')->default('nigeria');
                $table->decimal('allocated_minutes', 12, 2)->default(0);
                $table->decimal('daily_earning_cap', 36, 6)->default(0);
                $table->timestamp('starts_at');
                $table->timestamp('expires_at');
                $table->string('status', 24)->default('active');
                $table->timestamps();

                $table->index(
                    ['user_id', 'status', 'expires_at'],
                    'ss_user_status_exp_idx'
                );

                $table->index(
                    ['package_id', 'status'],
                    'ss_package_status_idx'
                );

                $table->index(
                    ['call_type_id', 'status'],
                    'ss_calltype_status_idx'
                );
            });
        }

        Schema::table('call_claims', function (Blueprint $table) {
            if (! Schema::hasColumn('call_claims', 'share_subscription_id')) {
                $table->foreignUuid('share_subscription_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('share_subscriptions')
                    ->nullOnDelete();
            }
        });

        Schema::table('minute_top_ups', function (Blueprint $table) {
            if (! Schema::hasColumn('minute_top_ups', 'share_subscription_id')) {
                $table->foreignUuid('share_subscription_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('share_subscriptions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('minute_top_ups', 'share_minutes')) {
                $table->decimal('share_minutes', 12, 2)
                    ->default(0)
                    ->after('daily_call_minutes');
            }

            if (! Schema::hasColumn('minute_top_ups', 'share_expires_at')) {
                $table->timestamp('share_expires_at')
                    ->nullable()
                    ->after('share_minutes');
            }
        });

        if (! Schema::hasTable('share_minute_allocations')) {
            Schema::create('share_minute_allocations', function (Blueprint $table) {
                $table->id();

                $table->foreignUuid('call_claim_id')
                    ->constrained('call_claims')
                    ->cascadeOnDelete();

                $table->foreignUuid('share_subscription_id')
                    ->constrained('share_subscriptions')
                    ->cascadeOnDelete();

                $table->foreignUuid('minute_top_up_id')
                    ->nullable()
                    ->constrained('minute_top_ups')
                    ->restrictOnDelete();

                $table->decimal('minutes', 12, 2);
                $table->timestamps();

                // Explicit short name avoids MySQL's 64-character limit.
                $table->index(
                    ['share_subscription_id', 'minute_top_up_id'],
                    'sma_subscription_topup_idx'
                );
            });
        } elseif (
            ! Schema::hasIndex(
                'share_minute_allocations',
                'sma_subscription_topup_idx'
            )
        ) {
            Schema::table('share_minute_allocations', function (Blueprint $table) {
                $table->index(
                    ['share_subscription_id', 'minute_top_up_id'],
                    'sma_subscription_topup_idx'
                );
            });
        }

        // Cut over the old shareholder wallet only once.
        // Existing positive balances are moved to a separate legacy balance.
        // New earnings continue to credit shareholder_balance.
        DB::table('users')
            ->where('shareholder_balance', '>', 0)
            ->where('legacy_shareholder_balance', '=', 0)
            ->update([
                'legacy_shareholder_balance' => DB::raw('shareholder_balance'),
                'shareholder_balance' => 0,
            ]);

        // No subscriptions are automatically created for existing package holders.
    }

    public function down(): void
    {
        // Preserve legacy balances and any earnings made after the cutover.
        if (
            Schema::hasColumn('users', 'shareholder_balance')
            && Schema::hasColumn('users', 'legacy_shareholder_balance')
        ) {
            DB::table('users')->update([
                'shareholder_balance' => DB::raw(
                    'shareholder_balance + legacy_shareholder_balance'
                ),
                'legacy_shareholder_balance' => 0,
            ]);
        }

        Schema::dropIfExists('share_minute_allocations');

        if (Schema::hasTable('minute_top_ups')) {
            Schema::table('minute_top_ups', function (Blueprint $table) {
                if (Schema::hasColumn('minute_top_ups', 'share_subscription_id')) {
                    $table->dropConstrainedForeignId('share_subscription_id');
                }

                $columns = [];

                foreach (['share_minutes', 'share_expires_at'] as $column) {
                    if (Schema::hasColumn('minute_top_ups', $column)) {
                        $columns[] = $column;
                    }
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (
            Schema::hasTable('call_claims')
            && Schema::hasColumn('call_claims', 'share_subscription_id')
        ) {
            Schema::table('call_claims', function (Blueprint $table) {
                $table->dropConstrainedForeignId('share_subscription_id');
            });
        }

        Schema::dropIfExists('share_subscriptions');

        foreach (['coupons', 'coupon_batches'] as $tableName) {
            if (
                Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, 'call_type_id')
            ) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('call_type_id');
                });
            }
        }

        if (Schema::hasColumn('users', 'legacy_shareholder_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('legacy_shareholder_balance');
            });
        }

        if (Schema::hasColumn('additional_minute_plans', 'share_minutes')) {
            Schema::table('additional_minute_plans', function (Blueprint $table) {
                $table->dropColumn('share_minutes');
            });
        }

        if (Schema::hasTable('call_types')) {
            Schema::table('call_types', function (Blueprint $table) {
                $columns = [
                    'price',
                    'agent_price',
                    'foreign_price',
                    'foreign_agent_price',
                    'region',
                    'monthly_share_minutes',
                    'share_term_days',
                    'max_daily_shareholder_earning',
                    'share_purchase_prompt_minutes',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('call_types', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table) {
                $columns = [];

                foreach (['monthly_share_minutes', 'share_term_days'] as $column) {
                    if (Schema::hasColumn('packages', $column)) {
                        $columns[] = $column;
                    }
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
