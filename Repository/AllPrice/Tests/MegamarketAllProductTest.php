<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Megamarket\Products\Repository\AllPrice\Tests;

use BaksDev\Megamarket\Products\Repository\AllPrice\MegamarketAllProductInterface;

use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group megamarket-products-price
 */
#[When(env: 'test')]
class MegamarketAllProductTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var MegamarketAllProductInterface $MegamarketAllProductInterface */
        $MegamarketAllProductInterface = self::getContainer()->get(MegamarketAllProductInterface::class);


        // $MegamarketAllProductInterface->product('018b8ca3-5001-7cb1-ad58-8aadd7d4b479');

        $MegamarketAllProductInterface
            ->event('018b9622-02b7-7622-a44c-d3aa522f292f');

        $MegamarketAllProductInterface
            ->offer(new ProductOfferUid('018b9622-02ba-70aa-84a5-695691d8fe0a'));

        $MegamarketAllProductInterface
            ->variation(new ProductVariationUid('018b9622-02ba-70aa-84a5-6956924d28a5'));

        $MegamarketAllProductInterface
            ->modification(new ProductModificationUid('018b9622-02ba-70aa-84a5-695692525c1a'));

        $products = $MegamarketAllProductInterface->findAll();



        self::assertCount(1, $products);


        $MegamarketAllProductInterface
            ->event('018b9622-02b7-7622-a44c-d3aa522f292f');

        $MegamarketAllProductInterface
            ->offer('018b1f6f-2374-7256-97b7-f40050d0ba63');

        $MegamarketAllProductInterface
            ->variation('018b1f6f-2378-75a9-abcf-34858d858629');

        $MegamarketAllProductInterface
            ->modification('018b1f6f-237c-7c1d-9c7a-3cb42223545e');

        $products = $MegamarketAllProductInterface->findAll();

        self::assertCount(1, $products);

    }

}
