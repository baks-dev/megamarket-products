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

namespace BaksDev\Megamarket\Products\Messenger\ProductStocks;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Products\Stocks\Messenger\Products\Recalculate\RecalculateProductMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class UpdateStocksMegamarketByRecalculate
{
    public function __construct(
        #[Target('megamarketProductsLogger')] private LoggerInterface $logger,
        private AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private MegamarketAllProductInterface $megamarketAllProduct,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    /**
     * Отправляем сообщение на обновление остатков при обновлении складского учета
     */
    public function __invoke(RecalculateProductMessage $product): void
    {
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

        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        foreach($profiles as $profile)
        {
            foreach($productsProduct as $itemProduct)
            {

                if(empty($itemProduct['product_price']))
                {
                    $this->logger->critical(
                        sprintf('Не указана стоимость продукции артикула %s', $itemProduct['product_article'])
                    );

                    continue;
                }

                /**
                 * Если не указаны параметры упаковки - остаток 0
                 * (на случай, если карточка с артикулом добавлена на Megamarket)
                 */
                if(
                    empty($itemProduct['product_parameter_length']) ||
                    empty($itemProduct['product_parameter_width']) ||
                    empty($itemProduct['product_parameter_height']) ||
                    empty($itemProduct['product_parameter_weight'])

                )
                {
                    $this->logger->critical(
                        sprintf('Не указаны параметры упаковки артикула %s', $itemProduct['product_article'])
                    );

                    continue;
                }

                $MegamarketProductStocksMessage = new MegamarketProductStocksMessage(
                    $profile,
                    $itemProduct['product_article']
                );

                /** Добавляем в очередь на обновление */
                $this->messageDispatch->dispatch(
                    $MegamarketProductStocksMessage,
                    stamps: [new MessageDelay('5 seconds')], // задержка 3 сек для обновления карточки
                    transport: (string) $profile
                );
            }
        }
    }
}
