<?php

namespace App\Service;

use App\Service\Feature\BrandFeatureBuilder;
use App\Service\Feature\CategoryFeatureBuilder;
use App\Service\Feature\ProductFeatureBuilder;
use App\Service\Feature\UserFeatureBuilder;

/**
 * Facade over the per-entity feature builders (see Service\Feature\), kept
 * as the single entry point consumed by FeatureController and
 * ExportFeaturesCommand.
 */
class FeatureService
{
    public function __construct(
        private ProductFeatureBuilder $productFeatureBuilder,
        private CategoryFeatureBuilder $categoryFeatureBuilder,
        private BrandFeatureBuilder $brandFeatureBuilder,
        private UserFeatureBuilder $userFeatureBuilder,
    ) {
    }

    public function getProductFeatures(): array
    {
        return $this->productFeatureBuilder->build();
    }

    public function getCategoryFeatures(): array
    {
        return $this->categoryFeatureBuilder->build();
    }

    public function getBrandFeatures(): array
    {
        return $this->brandFeatureBuilder->build();
    }

    public function getUserFeatures(): array
    {
        return $this->userFeatureBuilder->build();
    }
}
