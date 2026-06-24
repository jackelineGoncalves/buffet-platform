<?php

namespace Tests\Feature;

use App\Models\DiningSession;
use App\Models\Payment;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): DiningSession
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        return DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
    }

    public function test_payment_belongs_to_a_dining_session(): void
    {
        $session = $this->makeSession();

        $payment = Payment::create([
            'dining_session_id' => $session->id,
            'guests' => 3,
            'buffet_total' => 96.00,
            'extras_total' => 12.50,
            'waste_total' => 6.00,
            'tax' => 9.84,
            'total' => 124.34,
            'paid_at' => now(),
        ]);

        $this->assertTrue($payment->diningSession->is($session));
    }

    public function test_dining_session_has_one_payment(): void
    {
        $session = $this->makeSession();

        Payment::create([
            'dining_session_id' => $session->id,
            'guests' => 3,
            'buffet_total' => 96.00,
            'extras_total' => 0,
            'waste_total' => 0,
            'tax' => 8.40,
            'total' => 104.40,
            'paid_at' => now(),
        ]);

        $this->assertInstanceOf(Payment::class, $session->payment);
    }

    public function test_dining_session_can_only_have_one_payment(): void
    {
        $session = $this->makeSession();

        Payment::create([
            'dining_session_id' => $session->id,
            'guests' => 3,
            'buffet_total' => 96.00,
            'extras_total' => 0,
            'waste_total' => 0,
            'tax' => 8.40,
            'total' => 104.40,
            'paid_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Payment::create([
            'dining_session_id' => $session->id,
            'guests' => 3,
            'buffet_total' => 96.00,
            'extras_total' => 0,
            'waste_total' => 0,
            'tax' => 8.40,
            'total' => 104.40,
            'paid_at' => now(),
        ]);
    }
}
