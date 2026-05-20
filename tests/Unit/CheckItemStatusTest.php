<?php

namespace Tests\Unit;

use App\Enums\CheckItemStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckItemStatusTest extends TestCase
{
    #[Test]
    public function valid_transitions_are_allowed(): void
    {
        $this->assertTrue(CheckItemStatus::Draft->canTransitionTo(CheckItemStatus::Ordered));
        $this->assertTrue(CheckItemStatus::Draft->canTransitionTo(CheckItemStatus::Cancelled));
        $this->assertTrue(CheckItemStatus::Ordered->canTransitionTo(CheckItemStatus::Preparing));
        $this->assertTrue(CheckItemStatus::Ordered->canTransitionTo(CheckItemStatus::Cancelled));
        $this->assertTrue(CheckItemStatus::Preparing->canTransitionTo(CheckItemStatus::Ready));
        $this->assertTrue(CheckItemStatus::Ready->canTransitionTo(CheckItemStatus::Served));
    }

    #[Test]
    public function invalid_transitions_are_rejected(): void
    {
        // Saltarse estados intermedios
        $this->assertFalse(CheckItemStatus::Draft->canTransitionTo(CheckItemStatus::Preparing));
        $this->assertFalse(CheckItemStatus::Ordered->canTransitionTo(CheckItemStatus::Served));
        $this->assertFalse(CheckItemStatus::Preparing->canTransitionTo(CheckItemStatus::Served));

        // Volver atrás
        $this->assertFalse(CheckItemStatus::Served->canTransitionTo(CheckItemStatus::Ready));
        $this->assertFalse(CheckItemStatus::Ready->canTransitionTo(CheckItemStatus::Preparing));

        // Cancelar después de empezar a preparar
        $this->assertFalse(CheckItemStatus::Preparing->canTransitionTo(CheckItemStatus::Cancelled));

        // Desde un estado final
        $this->assertFalse(CheckItemStatus::Cancelled->canTransitionTo(CheckItemStatus::Ordered));
    }

    #[Test]
    public function final_states_are_detected(): void
    {
        $this->assertTrue(CheckItemStatus::Served->isFinal());
        $this->assertTrue(CheckItemStatus::Cancelled->isFinal());

        $this->assertFalse(CheckItemStatus::Draft->isFinal());
        $this->assertFalse(CheckItemStatus::Ordered->isFinal());
        $this->assertFalse(CheckItemStatus::Preparing->isFinal());
        $this->assertFalse(CheckItemStatus::Ready->isFinal());
    }

    #[Test]
    public function kitchen_visibility_filters_correct_statuses(): void
    {
        $this->assertTrue(CheckItemStatus::Ordered->isVisibleInKitchen());
        $this->assertTrue(CheckItemStatus::Preparing->isVisibleInKitchen());

        $this->assertFalse(CheckItemStatus::Draft->isVisibleInKitchen());
        $this->assertFalse(CheckItemStatus::Ready->isVisibleInKitchen());
        $this->assertFalse(CheckItemStatus::Served->isVisibleInKitchen());
        $this->assertFalse(CheckItemStatus::Cancelled->isVisibleInKitchen());
    }
}
