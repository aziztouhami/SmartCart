<?php

namespace App\Tests\Unit\Recommendation;

use App\ML\MatrixFactorizationTrainer;
use App\Repository\InteractionRepository;
use App\Service\Recommendation\CollaborativeFilteringService;
use PHPUnit\Framework\TestCase;

class CollaborativeFilteringServiceTest extends TestCase
{
    private InteractionRepository $interactionRepository;
    private MatrixFactorizationTrainer $matrixFactorization;
    private CollaborativeFilteringService $service;

    protected function setUp(): void
    {
        $this->interactionRepository = $this->createMock(InteractionRepository::class);
        $this->matrixFactorization = $this->createMock(MatrixFactorizationTrainer::class);

        $this->service = new CollaborativeFilteringService(
            $this->interactionRepository,
            $this->matrixFactorization,
        );
    }

    public function testBuildTasteMatrixWeighsViewsCartsAndPurchases(): void
    {
        $this->interactionRepository->method('findAllForTasteMatrix')->willReturn([
            ['userId' => 1, 'productId' => 10, 'type' => 'view', 'value' => null],
            ['userId' => 1, 'productId' => 11, 'type' => 'cart', 'value' => null],
            ['userId' => 1, 'productId' => 12, 'type' => 'purchase', 'value' => null],
        ]);

        $matrix = $this->service->buildTasteMatrix();

        $this->assertSame(1.0, $matrix[1][10]); // WEIGHT_VIEW
        $this->assertSame(3.0, $matrix[1][11]); // WEIGHT_CART
        $this->assertSame(5.0, $matrix[1][12]); // WEIGHT_PURCHASE
    }

    public function testBuildTasteMatrixWeighsRatingsByThreshold(): void
    {
        $this->interactionRepository->method('findAllForTasteMatrix')->willReturn([
            ['userId' => 1, 'productId' => 10, 'type' => 'rating', 'value' => 80],  // good (>= 60)
            ['userId' => 1, 'productId' => 11, 'type' => 'rating', 'value' => 60],  // good (boundary, >= 60)
            ['userId' => 1, 'productId' => 12, 'type' => 'rating', 'value' => 50],  // neutral (40-59)
            ['userId' => 1, 'productId' => 13, 'type' => 'rating', 'value' => 39],  // bad (< 40)
            ['userId' => 1, 'productId' => 14, 'type' => 'rating', 'value' => null], // no value => neutral
        ]);

        $matrix = $this->service->buildTasteMatrix();

        $this->assertSame(5.0, $matrix[1][10]);
        $this->assertSame(5.0, $matrix[1][11]);
        $this->assertSame(1.0, $matrix[1][12]);
        $this->assertSame(-3.0, $matrix[1][13]);
        $this->assertSame(1.0, $matrix[1][14]);
    }

    public function testBuildTasteMatrixTreatsUnknownTypesAsViews(): void
    {
        $this->interactionRepository->method('findAllForTasteMatrix')->willReturn([
            ['userId' => 1, 'productId' => 10, 'type' => 'wishlist', 'value' => null],
        ]);

        $matrix = $this->service->buildTasteMatrix();

        $this->assertSame(1.0, $matrix[1][10]);
    }

    public function testBuildTasteMatrixAccumulatesRepeatedInteractionsForSamePair(): void
    {
        $this->interactionRepository->method('findAllForTasteMatrix')->willReturn([
            ['userId' => 1, 'productId' => 10, 'type' => 'view', 'value' => null],
            ['userId' => 1, 'productId' => 10, 'type' => 'view', 'value' => null],
            ['userId' => 1, 'productId' => 10, 'type' => 'cart', 'value' => null],
        ]);

        $matrix = $this->service->buildTasteMatrix();

        $this->assertSame(5.0, $matrix[1][10]); // 1.0 + 1.0 + 3.0
    }

    public function testBuildTasteMatrixKeepsUsersSeparate(): void
    {
        $this->interactionRepository->method('findAllForTasteMatrix')->willReturn([
            ['userId' => 1, 'productId' => 10, 'type' => 'purchase', 'value' => null],
            ['userId' => 2, 'productId' => 10, 'type' => 'view', 'value' => null],
        ]);

        $matrix = $this->service->buildTasteMatrix();

        $this->assertSame(5.0, $matrix[1][10]);
        $this->assertSame(1.0, $matrix[2][10]);
    }

    public function testBuildTasteMatrixReturnsEmptyArrayWhenNoInteractions(): void
    {
        $this->interactionRepository->method('findAllForTasteMatrix')->willReturn([]);

        $this->assertSame([], $this->service->buildTasteMatrix());
    }

    public function testTrainDelegatesToMatrixFactorizationTrainer(): void
    {
        $tasteMatrix = [1 => [10 => 5.0]];
        $trainedModel = ['mu' => 1.0, 'userFactors' => [], 'itemFactors' => [], 'userBias' => [], 'itemBias' => []];

        $this->matrixFactorization->expects($this->once())
            ->method('train')
            ->with($tasteMatrix)
            ->willReturn($trainedModel);

        $result = $this->service->train($tasteMatrix);

        $this->assertSame($trainedModel, $result);
    }

    public function testPredictForUserSkipsProductsTheUserAlreadyHasARatingFor(): void
    {
        $this->matrixFactorization->expects($this->never())->method('predict');

        $result = $this->service->predictForUser(1, [10 => 5.0], [], [10]);

        $this->assertSame([], $result);
    }

    public function testPredictForUserDelegatesToMatrixFactorizationForRemainingCandidates(): void
    {
        $model = ['mu' => 1.0];
        $this->matrixFactorization->method('predict')
            ->willReturnMap([
                [$model, 1, 20, 0.7],
                [$model, 1, 30, 0.9],
            ]);

        $result = $this->service->predictForUser(1, [10 => 5.0], $model, [10, 20, 30]);

        $this->assertSame([20 => 0.7, 30 => 0.9], $result);
    }
}
