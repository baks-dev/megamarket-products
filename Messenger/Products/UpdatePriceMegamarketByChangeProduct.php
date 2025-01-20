<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePriceMegamarketByChangeProduct
{
    public function __construct(
        #[Target('megamarketProductsLogger')] private LoggerInterface $logger,
        private AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private MegamarketAllProductInterface $megamarketAllProduct,
        private MessageDispatchInterface $messageDispatch,
    ) {}

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
                )
                {
                    $this->logger->critical(
                        sprintf('megamarket-products: Не указаны параметры упаковки артикула %s', $product['product_article'])
                    );

                    continue;
                }

                if(empty($product['product_price']))
                {
                    continue;
                }

                $price = new Money($product['product_price'], true);

                $MegamarketProductPriceMessage = new MegamarketProductPriceMessage(
                    $profile,
                    $product['product_article'],
                    $price,
                    $currency
                );

                $MegamarketProductPriceMessage->setParameter(
                    $product['product_parameter_width'],
                    $product['product_parameter_height'],
                    $product['product_parameter_length']
                );

                $this->messageDispatch->dispatch(
                    $MegamarketProductPriceMessage,
                    transport: 'megamarket-products'
                );
            }
        }
    }
}
