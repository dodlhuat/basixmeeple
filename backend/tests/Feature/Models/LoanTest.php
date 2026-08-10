<?php

namespace Tests\Feature\Models;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_fields_are_cast(): void
    {
        $loan = Loan::factory()->create([
            'loaned_at' => '2026-01-01 10:00:00',
            'due_date' => '2026-02-01',
            'returned_at' => '2026-01-20 18:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $loan->loaned_at);
        $this->assertInstanceOf(Carbon::class, $loan->due_date);
        $this->assertInstanceOf(Carbon::class, $loan->returned_at);
    }

    public function test_a_loan_can_exist_without_a_registered_borrower(): void
    {
        $loan = Loan::factory()->create(['borrower_user_id' => null, 'borrower_name' => 'Nachbarin']);

        $this->assertNull($loan->borrower);
        $this->assertSame('Nachbarin', $loan->borrower_name);
    }

    public function test_borrower_relation_resolves_when_a_user_is_set(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create(['borrower_user_id' => $user->id]);

        $this->assertTrue($loan->borrower->is($user));
    }

    public function test_a_loans_borrower_user_is_nulled_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create(['borrower_user_id' => $user->id]);

        $user->delete();

        $this->assertNull($loan->fresh()->borrower_user_id);
    }
}
