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

#[AsMessageHandler(priority: 100)]
final class UpdateStocksMegamarketByChangeStatus
{
    private MessageDispatchInterface $messageDispatch;
    private OrderProductsInterface $orderProducts;
    private MegamarketAllProductInterface $megamarketAllProduct;
    private AllProfileMegamarketTokenInterface $allProfileMegamarketToken;
    private LoggerInterface $logger;

    public function __construct(
        OrderProductsInterface $orderProducts,
        MegamarketAllProductInterface $megamarketAllProduct,
        AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        LoggerInterface $megamarketProductsLogger,
        MessageDispatchInterface $messageDispatch,
    ) {
        $this->messageDispatch = $messageDispatch;
        $this->orderProducts = $orderProducts;
        $this->megamarketAllProduct = $megamarketAllProduct;
        $this->allProfileMegamarketToken = $allProfileMegamarketToken;
        $this->logger = $megamarketProductsLogger;
    }

    /**
     * Обновляем остатки Megamarket при изменении статусов заказов
     */
    public function __invoke(OrderMessage $message): void
    {
        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        /** Получаем всю продукцию в заказе */
        $productsOrder = $this->orderProducts
            ->fetchAllOrderProducts($message->getId());

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
                foreach($profiles as $profile)
                {
                    /** Если не указана стоимость - остаток 0 */
                    $quantity = $itemProduct['product_price'] ? max(0, $itemProduct['product_quantity']) : 0;

                    /**
                     * Если не указаны параметры упаковки - остаток 0
                     * (на случай, если карточка с артикулом добавлена на Megamarket)
                     */
                    if(
                        $quantity !== 0 && (
                            empty($itemProduct['product_parameter_length']) ||
                            empty($itemProduct['product_parameter_width']) ||
                            empty($itemProduct['product_parameter_height']) ||
                            empty($itemProduct['product_parameter_weight'])
                        )
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
}
