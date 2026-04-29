<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search;

use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchQueryData;
use Padosoft\ProductImageDiscovery\Services\Search\Data\ProductImageSearchResultCollection;

interface ProductImageSearchProviderInterface
{
    public function searchImages(ProductImageSearchQueryData $query): ProductImageSearchResultCollection;

    public function searchWeb(ProductImageSearchQueryData $query): ProductImageSearchResultCollection;

    public function supportsImageSearch(): bool;

    public function supportsSiteFilter(): bool;
}
