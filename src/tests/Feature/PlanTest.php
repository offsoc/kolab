<?php

namespace Tests\Feature;

use App\Entitlement;
use App\Plan;
use App\Sku;
use Tests\TestCase;

class PlanTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Plan::where('title', 'test-plan')->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Plan::where('title', 'test-plan')->delete();
    }

    /**
     * Tests for plan attributes localization
     */
    public function testPlanLocalization(): void
    {
        $plan = Plan::create([
            'title' => 'test-plan',
            'description' => [
                'en' => 'Plan-EN',
                'de' => 'Plan-DE',
            ],
            'name' => 'Test',
        ]);

        $this->assertSame('Plan-EN', $plan->description);
        $this->assertSame('Test', $plan->name);

        $plan->save();
        $plan = Plan::where('title', 'test-plan')->first();

        $this->assertSame('Plan-EN', $plan->description);
        $this->assertSame('Test', $plan->name);
        $this->assertSame('Plan-DE', $plan->getTranslation('description', 'de'));
        $this->assertSame('Test', $plan->getTranslation('name', 'de'));

        $plan->setTranslation('name', 'de', 'Prüfung')->save();

        $this->assertSame('Prüfung', $plan->getTranslation('name', 'de'));
        $this->assertSame('Test', $plan->getTranslation('name', 'en'));

        $plan = Plan::where('title', 'test-plan')->first();

        $this->assertSame('Prüfung', $plan->getTranslation('name', 'de'));
        $this->assertSame('Test', $plan->getTranslation('name', 'en'));

        // TODO: Test system locale change
    }

    /**
     * Tests for Plan::hasDomain()
     */
    public function testHasDomain(): void
    {
        $plan = Plan::where('title', 'individual')->first();

        $this->assertTrue($plan->hasDomain() === false);

        $plan = Plan::where('title', 'group')->first();

        $this->assertTrue($plan->hasDomain() === true);
    }
}
