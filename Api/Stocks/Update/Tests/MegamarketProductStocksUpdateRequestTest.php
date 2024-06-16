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

namespace BaksDev\Megamarket\Products\Api\Stocks\Update\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Megamarket\Products\Api\Price\Update\MegamarketProductPriceUpdateRequest;
use BaksDev\Megamarket\Products\Api\Stocks\Update\MegamarketProductStocksUpdateRequest;
use BaksDev\Megamarket\Type\Authorization\MegamarketAuthorizationToken;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group megamarket-products
 * @group megamarket-products-stock
 */
#[When(env: 'test')]
class MegamarketProductStocksUpdateRequestTest extends KernelTestCase
{
    private static MegamarketAuthorizationToken $Authorization;

    public static function setUpBeforeClass(): void
    {
        self::$Authorization = new MegamarketAuthorizationToken(
            new UserProfileUid(),
            $_SERVER['TEST_MEGAMARKET_TOKEN'],
            $_SERVER['TEST_MEGAMARKET_COMPANY']
        );
    }

    public function testUseCase(): void
    {
        /** @var MegamarketProductStocksUpdateRequest $request */
        $request = self::getContainer()->get(MegamarketProductStocksUpdateRequest::class);
        $request->TokenHttpClient(self::$Authorization);

        $data = $request
            ->article('PL02-20-265-45-108V')
            ->total(1)
            ->update();

        self::assertIsBool($data);

    }

}
