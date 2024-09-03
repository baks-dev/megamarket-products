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

namespace BaksDev\Megamarket\Products\Messenger\Orders;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderProducts\OrderProductsInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler(priority: 100)]
final class UpdateStocksMegamarketByChangeStatus
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly OrderProductsInterface $orderProducts,
        private readonly MegamarketAllProductInterface $megamarketAllProduct,
        private readonly AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private readonly MessageDispatchInterface $messageDispatch,
        LoggerInterface $megamarketProductsLogger,
    ) {
        $this->logger = $megamarketProductsLogger;
    }

    /**
     * Обновляем остатки Megamarket при изменении статусов заказов
     */
    public function __invoke(OrderMessage $message): void
    {
        /** Получаем всю продукцию в заказе */
        $productsOrder = $this->orderProducts
            ->order($message->getId())
            ->findAllProducts();

        if(empty($productsOrder))
        {
            return;
        }

        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        foreach($profiles as $profile)
        {
            foreach($productsOrder as $itemOrder)
            {
                /** Получаем активное состояние продукта */
                $productsProduct = $this->megamarketAllProduct
                    ->event(new ProductEventUid($itemOrder['product_event']))
                    ->offer(new ProductOfferUid($itemOrder['product_offer']))
                    ->variation(new ProductVariationUid($itemOrder['product_variation']))
                    ->modification(new ProductModificationUid($itemOrder['product_modification']))
                    ->findAll();

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

                    ) {
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
                        stamps: [new DelayStamp(3000)], // задержка 3 сек для обновления карточки
                        transport: (string) $profile
                    );
                }
            }
        }
    }
}
