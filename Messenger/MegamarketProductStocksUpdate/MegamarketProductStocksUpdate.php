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

namespace BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Api\Stocks\UpdateMegamarketProductStocksRequest;
use BaksDev\Products\Product\Repository\ProductQuantity\ProductQuantityByArticle\ProductQuantityByArticleInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 100)]
final readonly class MegamarketProductStocksUpdate
{
    public function __construct(
        #[Target('megamarketProductsLogger')] private LoggerInterface $logger,
        private UpdateMegamarketProductStocksRequest $UpdateMegamarketProductStocksRequest,
        private ProductQuantityByArticleInterface $productQuantityByArticle,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    /**
     * Обновляем наличие продукции
     */
    public function __invoke(MegamarketProductStocksMessage $message): void
    {
        /** Получаем доступное наличие по артикулу */
        $Quantity = $this->productQuantityByArticle->find($message->getArticle()) ?: 0;

        if($Quantity === false)
        {
            $this->logger->info(
                sprintf('megamarket-products: Невозможно определить товар c артикулом %s для обновления остатка', $message->getArticle()),
                [self::class.':'.__LINE__]
            );
        }

        $update = $this->UpdateMegamarketProductStocksRequest
            ->profile($message->getProfile())
            ->article($message->getArticle())
            ->total($Quantity)
            ->update();


        if($update === false)
        {
            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('5 seconds')],
                transport: (string) $message->getProfile()
            );

            $this->logger->critical(
                message: sprintf('megamarket-products: Пробуем обновить остатки %s через 5 секунд', $message->getArticle()),
                context: [self::class.':'.__LINE__]
            );

            return;
        }

        $this->logger->info(
            sprintf(
                'Обновили остатки товара с артикулом %s => %s',
                $message->getArticle(),
                $Quantity
            ),
            [
                self::class.':'.__LINE__,
                'profile' => (string) $message->getProfile()
            ]
        );
    }
}
