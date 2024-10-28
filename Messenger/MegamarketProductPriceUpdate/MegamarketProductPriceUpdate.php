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

namespace BaksDev\Megamarket\Products\Messenger\MegamarketProductPriceUpdate;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Api\Price\UpdateMegamarketProductPriceRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MegamarketProductPriceUpdate
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly UpdateMegamarketProductPriceRequest $UpdateMegamarketProductPriceRequest,
        private readonly MessageDispatchInterface $messageDispatch,
        LoggerInterface $megamarketProductsLogger,
    )
    {
        $this->logger = $megamarketProductsLogger;
    }

    /**
     * Обновляем базовую цену товара на Megamarket
     */
    public function __invoke(MegamarketProductPriceMessage $message): void
    {
        /** Не обновляем нулевую стоимость */
        if(empty($message->getPrice()?->getValue()))
        {
            return;
        }

        $update = $this->UpdateMegamarketProductPriceRequest
            ->profile($message->getProfile())
            ->article($message->getArticle())
            ->price(
                $message->getPrice(),
                $message->getWidth(),
                $message->getHeight(),
                $message->getLength(),
            )
            ->update();


        if($update === false)
        {
            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('5 seconds')],
                transport: (string) $message->getProfile()
            );

            return;
        }

        $this->logger->info(
            sprintf(
                'Обновили стоимость товара с артикулом %s => %s',
                $message->getArticle(),
                $message->getPrice()
            ),
            [self::class.':'.__LINE__, 'profile' => (string) $message->getProfile()]
        );
    }
}
