<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Megamarket\Products\Messenger\Products;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductPriceUpdate\MegamarketProductPriceMessage;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllPrice\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Products\Product\Messenger\ProductMessage;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Reference\Currency\Type\Currency;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Yandex\Market\Products\Repository\Card\ProductYaMarketCard\ProductsYaMarketCardInterface;
use BaksDev\Yandex\Market\Products\Type\Card\Event\YaMarketProductsCardEventUid;
use BaksDev\Yandex\Market\Products\Type\Card\Id\YaMarketProductsCardUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdatePriceMegamarketByChangeProduct
{
    private MessageDispatchInterface $messageDispatch;
    private AllProfileMegamarketTokenInterface $allProfileMegamarketToken;
    private MegamarketAllProductInterface $megamarketAllProduct;
    private LoggerInterface $logger;

    public function __construct(
        AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        MegamarketAllProductInterface $megamarketAllProduct,
        LoggerInterface $megamarketProductsLogger,
        MessageDispatchInterface $messageDispatch,
    ) {
        $this->messageDispatch = $messageDispatch;
        $this->allProfileMegamarketToken = $allProfileMegamarketToken;
        $this->megamarketAllProduct = $megamarketAllProduct;
        $this->logger = $megamarketProductsLogger;
    }

    /**
     * Обновляем стоимость Megamarket при изменении системной карточки
     */
    public function __invoke(ProductMessage $message): void
    {
        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        /** Получаем активное состояние продукта */
        $productsProduct = $this->megamarketAllProduct
            ->product($message->getId())
            ->findAll();

        foreach($productsProduct as $product)
        {
            foreach($profiles as $profile)
            {
                $currency = new Currency($product['product_currency']);

                /** Если не указаны параметры упаковки - не обновляем */
                if(
                    empty($product['product_parameter_length']) ||
                    empty($product['product_parameter_width']) ||
                    empty($product['product_parameter_height']) ||
                    empty($product['product_parameter_weight'])
                ) {
                    $this->logger->critical(
                        sprintf('Не указаны параметры упаковки артикула %s', $product['product_article'])
                    );

                    continue;
                }

                /**
                 * Перерасчет стоимости продукции
                 */

                // 15% комиссии
                $percent = $product['product_price'] / 100 * 15;

                // длина + ширина + высота * 5 и переводим с копейками * 100
                $rate = ($product['product_parameter_length'] + $product['product_parameter_width'] + $product['product_parameter_height']) / 2 * 100;
                $result_price = $product['product_price'] + $percent + $rate;
                $price = new Money($result_price / 100);

                $MegamarketProductPriceMessage = new MegamarketProductPriceMessage(
                    $profile,
                    $product['product_article'],
                    $price,
                    $currency
                );

                $this->messageDispatch->dispatch(
                    $MegamarketProductPriceMessage,
                    transport: 'megamarket-products'
                );
            }
        }
    }
}
