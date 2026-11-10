<?php

namespace Tests\Unit;

use App\Models\SubscriptionPlan;
use Tests\TestCase;

class SubscriptionPlanModelTest extends TestCase
{
    public function test_plan_model_exposes_group_scope_and_package_withdrawal_fields(): void
    {
        $plan = new SubscriptionPlan();

        $this->assertContains('subscription_group_id', $plan->getFillable());
        $this->assertNotContains('global_listing_access', $plan->getFillable());
        $this->assertContains('eligible_app_store', $plan->getFillable());
        $this->assertContains('minimum_withdrawal_minor', $plan->getFillable());
        $this->assertContains('withdrawal_days', $plan->getFillable());
        $this->assertContains('daily_task_limit', $plan->getFillable());
        $this->assertArrayHasKey('withdrawal_days', $plan->getCasts());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $plan->group());
    }
}
