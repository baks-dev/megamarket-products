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

namespace BaksDev\Megamarket\Products\Messenger\MegamarketProductPriceUpdate;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Messenger\MegamarketTokenMessage;
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Reference\Currency\Type\Currency;
use BaksDev\Reference\Money\Type\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class MegamarketProductPriceUpdateByTokenSettingsHandler
{
    public function __construct(
        private MegamarketAllProductInterface $allProductPrice,
        private AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    /**
     * Метод обновляет все цены при обновлении настроек
     */
    public function __invoke(MegamarketTokenMessage $message): void
    {

        /** Получаем активные токены авторизации профилей Yandex Market */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        if(false === $profiles->valid())
        {
            return;
        }

        $profiles = iterator_to_array($profiles);


        /**
         * Получаем всю продукцию
         */
        $products = $this->allProductPrice->findAll();

        foreach($products as $product)
        {
            // Не обновляем карточку без цены
            if(empty($product['product_price']))
            {
                continue;
            }

            // Не обновляем карточку без параметров упаковки
            if(
                empty($product['product_parameter_length']) ||
                empty($product['product_parameter_width']) ||
                empty($product['product_parameter_height']) ||
                empty($product['product_parameter_weight'])
            )
            {
                continue;
            }

            $price = new Money($product['product_price'], true);
            $currency = new Currency($product['product_currency']);

            foreach($profiles as $profile)
            {
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
                    message: $MegamarketProductPriceMessage,
                    transport: 'megamarket-products'
                );
            }
        }
    }
}
