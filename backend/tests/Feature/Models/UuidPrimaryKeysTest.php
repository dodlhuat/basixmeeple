<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Expansion;
use App\Models\Game;
use App\Models\Loan;
use App\Models\PersonalAccessToken;
use App\Models\PlaySession;
use App\Models\SessionPlayer;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UuidPrimaryKeysTest extends TestCase
{
    use RefreshDatabase;

    public static function modelFactories(): array
    {
        return [
            'User' => [fn () => User::factory()->create()],
            'Game' => [fn () => Game::factory()->create()],
            'Expansion' => [fn () => Expansion::factory()->create()],
            'Category' => [fn () => Category::factory()->create()],
            'Collection' => [fn () => Collection::factory()->create()],
            'PlaySession' => [fn () => PlaySession::factory()->create()],
            'SessionPlayer' => [fn () => SessionPlayer::factory()->create()],
            'Loan' => [fn () => Loan::factory()->create()],
            'WishlistItem' => [fn () => WishlistItem::factory()->create()],
        ];
    }

    #[DataProvider('modelFactories')]
    public function test_model_receives_a_uuid_primary_key(\Closure $make): void
    {
        $model = $make();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $model->getKey(),
        );
        $this->assertIsString($model->getKey());
    }

    public function test_personal_access_token_receives_a_uuid_primary_key(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->assertInstanceOf(PersonalAccessToken::class, $token->accessToken);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $token->accessToken->id,
        );
    }

    public function test_sanctum_resolves_a_plain_text_token_back_to_its_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $resolved = PersonalAccessToken::findToken($token->plainTextToken);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->tokenable->is($user));
    }
}
