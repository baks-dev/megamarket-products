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

namespace BaksDev\Megamarket\Products\Messenger\ProductStocks;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final class UpdateStocksMegamarketByIncoming
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private MessageDispatchInterface $messageDispatch;
    private MegamarketAllProductInterface $megamarketAllProduct;
    private LoggerInterface $logger;
    private AllProfileMegamarketTokenInterface $allProfileMegamarketToken;

    public function __construct(
        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        MegamarketAllProductInterface $megamarketAllProduct,
        LoggerInterface $megamarketProductsLogger,
        AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
    ) {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->messageDispatch = $messageDispatch;
        $this->megamarketAllProduct = $megamarketAllProduct;
        $this->logger = $megamarketProductsLogger;
        $this->allProfileMegamarketToken = $allProfileMegamarketToken;
    }

    /**
     * Обновляет складские остатки при поступлении на склад
     */
    public function __invoke(ProductStockMessage $message): void
    {

        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        $this->entityManager->clear();

        if(!$ProductStockEvent)
        {
            return;
        }

        /**
         * Если Статус заявки не является Incoming «Приход на склад»
         */
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusIncoming::class))
        {
            return;
        }

        // Получаем всю продукцию в ордере со статусом Incoming
        $productsStocks = $this->productStocks->getProductsIncomingStocks($message->getId());

        if(empty($productsStocks))
        {
            return;
        }

        /** Получаем все профили для обновления */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        /** @var ProductStockProduct $itemStocks */
        foreach($productsStocks as $itemStocks)
        {
            /** Получаем активное состояние продукта */
            $productsProduct = $this->megamarketAllProduct
                ->product($itemStocks->getProduct())
                ->offer($itemStocks->getOffer())
                ->variation($itemStocks->getVariation())
                ->modification($itemStocks->getModification())
                ->findAll();

            if(empty($productsProduct))
            {
                continue;
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
}
