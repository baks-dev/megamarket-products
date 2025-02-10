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

namespace BaksDev\Megamarket\Products\Messenger\Orders;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderProducts\OrderProductRepositoryDTO;
use BaksDev\Orders\Order\Repository\OrderProducts\OrderProductsInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 100)]
final readonly class UpdateStocksMegamarketByChangeStatus
{
    public function __construct(
        #[Target('megamarketProductsLogger')] private LoggerInterface $logger,
        private OrderProductsInterface $orderProducts,
        private MegamarketAllProductInterface $megamarketAllProduct,
        private AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    /**
     * Обновляем остатки Megamarket при изменении статусов заказов
     */
    public function __invoke(OrderMessage $message): void
    {
        /** Получаем всю продукцию в заказе */
        $products = $this->orderProducts
            ->order($message->getId())
            ->findAllProducts();

        if(false === ($products || $products->valid()))
        {
            return;
        }

        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        if(false === ($profiles || $profiles->valid()))
        {
            return;
        }

        $profiles = iterator_to_array($profiles);

        /** @var OrderProductRepositoryDTO $product */
        foreach($products as $product)
        {
            /** Получаем активное состояние продукта */
            $productsProduct = $this->megamarketAllProduct
                ->event($product->getProductEvent())
                ->offer($product->getProductOffer())
                ->variation($product->getProductVariation())
                ->modification($product->getProductModification())
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

                )
                {
                    $this->logger->critical(
                        sprintf('Не указаны параметры упаковки артикула %s', $itemProduct['product_article'])
                    );

                    continue;
                }

                /**
                 * Добавляем в очередь на обновление
                 */
                foreach($profiles as $profile)
                {
                    $MegamarketProductStocksMessage = new MegamarketProductStocksMessage(
                        profile: $profile,
                        article: $itemProduct['product_article']
                    );

                    $this->messageDispatch->dispatch(
                        message: $MegamarketProductStocksMessage,
                        stamps: [new MessageDelay('5 seconds')],
                        transport: (string) $profile
                    );
                }
            }
        }

    }
}
