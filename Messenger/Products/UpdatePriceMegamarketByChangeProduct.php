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

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductPriceUpdate\MegamarketProductPriceMessage;
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Products\Product\Messenger\ProductMessage;
use BaksDev\Reference\Currency\Type\Currency;
use BaksDev\Reference\Money\Type\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdatePriceMegamarketByChangeProduct
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private readonly MegamarketAllProductInterface $megamarketAllProduct,
        private readonly MessageDispatchInterface $messageDispatch,
        LoggerInterface $megamarketProductsLogger,
    ) {

        $this->logger = $megamarketProductsLogger;
    }

    /**
     * Обновляем стоимость Megamarket при изменении системной карточки
     */
    public function __invoke(ProductMessage $message): void
    {
        /** Получаем активное состояние продукта */
        $productsProduct = $this->megamarketAllProduct
            ->product($message->getId())
            ->findAll();

        if(empty($productsProduct))
        {
            return;
        }

        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        foreach($profiles as $profile)
        {
            foreach($productsProduct as $product)
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
                $percent = $product['product_price'] / 100 * 5;

                // длина + ширина + высота * 5 и переводим с копейками * 100
                //$rate = ($product['product_parameter_length'] + $product['product_parameter_width'] + $product['product_parameter_height']) / 2 * 100;
                $rate = 0;
                $result_price = $product['product_price'] + $percent + $rate;
                $price = new Money($result_price, true);

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
