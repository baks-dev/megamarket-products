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

namespace BaksDev\Megamarket\Products\Messenger\ProductStocks;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllPrice\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Products\Stocks\Messenger\Products\Recalculate\RecalculateProductMessage;
use BaksDev\Yandex\Market\Products\Messenger\Card\YaMarketProductsStocksUpdate\YaMarketProductsStocksMessage;
use BaksDev\Yandex\Market\Products\Repository\Card\CardByCriteria\YaMarketProductsCardByCriteriaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class UpdateStocksMegamarketByRecalculate
{
    private MessageDispatchInterface $messageDispatch;
    private AllProfileMegamarketTokenInterface $allProfileMegamarketToken;
    private MegamarketAllProductInterface $megamarketAllProduct;
    private LoggerInterface $logger;

    public function __construct(
        AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        MegamarketAllProductInterface $megamarketAllProduct,
        LoggerInterface $megamarketProductsLogger,
        MessageDispatchInterface $messageDispatch
    ) {


        $this->allProfileMegamarketToken = $allProfileMegamarketToken;
        $this->messageDispatch = $messageDispatch;
        $this->megamarketAllProduct = $megamarketAllProduct;
        $this->logger = $megamarketProductsLogger;
    }

    /**
     * Отправляем сообщение на обновление остатков при обновлении складского учета
     */
    public function __invoke(RecalculateProductMessage $product): void
    {
        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        /** Получаем активное состояние продукта */
        $productsProduct = $this->megamarketAllProduct
            ->product($product->getProduct())
            ->offer($product->getOffer())
            ->variation($product->getVariation())
            ->modification($product->getModification())
            ->findAll();

        if(empty($productsProduct))
        {
            return;
        }

        foreach($productsProduct as $itemProduct)
        {
            foreach($profiles as $profile)
            {
                /** Если не указана стоимость - остаток 0 */
                $quantity = $itemProduct['product_price'] ? $itemProduct['product_quantity'] : 0;

                /** Если не указаны параметры упаковки - остаток 0 */
                if(
                    empty($itemProduct['product_parameter_length']) ||
                    empty($itemProduct['product_parameter_width']) ||
                    empty($itemProduct['product_parameter_height']) ||
                    empty($itemProduct['product_parameter_weight'])
                ) {
                    $quantity = 0;

                    $this->logger->critical(
                        sprintf('Не указаны параметры упаковки артикула %s', $itemProduct['product_article'])
                    );
                }

                $MegamarketProductStocksMessage = new MegamarketProductStocksMessage(
                    $profile,
                    $itemProduct['product_article'],
                    $quantity
                );

                /** Добавляем в очередь на обновление */
                $this->messageDispatch->dispatch(
                    $MegamarketProductStocksMessage,
                    transport: 'megamarket-products'
                );
            }
        }
    }
}
