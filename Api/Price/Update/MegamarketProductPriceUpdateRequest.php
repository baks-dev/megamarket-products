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

namespace BaksDev\Megamarket\Products\Api\Price\Update;

use BaksDev\Megamarket\Api\Megamarket;
use BaksDev\Reference\Money\Type\Money;
use InvalidArgumentException;
use stdClass;

final class MegamarketProductPriceUpdateRequest extends Megamarket
{
    private int $retry = 0;

    private ?string $article = null;

    private ?int $price = null;

    public function article(string $article): self
    {
        $this->article = $article;
        return $this;
    }

    public function price(
        int|float|Money $price,
        int $width = 0,
        int $height = 0,
        int $length = 0
    ): self {

        if($price instanceof Money)
        {
            $price = $price->getOnlyPositive();
        }

        /**
         * Добавляем к стоимости Торговую наценку
         */
        if($this->getPercent())
        {
            $percent = $price / 100 * $this->getPercent();
            $price += $percent;
        }


        /**
         * Добавляем к стоимости Надбавку за габариты товара
         */
        if($this->getRate())
        {
            $rate = ($width + $height + $length) / 2 * 100;
            $price += $rate;
        }

        $this->price = (int) $price;

        return $this;
    }

    public function update(): bool
    {
        /**
         * Выполнять операции запроса ТОЛЬКО в PROD окружении
         */
        if($this->isExecuteEnvironment() === false)
        {
            return true;
        }

        if(empty($this->article))
        {
            throw new InvalidArgumentException('Invalid Argument $article');
        }

        if(empty($this->price))
        {
            throw new InvalidArgumentException('Invalid Argument price');
        }

        $response = $this->TokenHttpClient()->request(
            'POST',
            '/api/merchantIntegration/v1/offerService/manualPrice/save',
            ['json' => [
                'meta' => new stdClass(),
                'data' => [
                    'token' => $this->getToken(),
                    'prices' => [
                        [
                            "offerId" => $this->article,
                            "price" => $this->price
                        ]
                    ]
                ]
            ]]
        );

        $content = $response->toArray(false);

        if(isset($content['error']))
        {
            // Если истрачено 5 попыток с задержкой в прогрессии
            if($this->retry > 32)
            {
                $content['error'][0] = self::class.':'.__LINE__;

                $this->logger->critical(sprintf('Megamarket: Ошибка обновления стоимости артикула %s', $this->article), $content['error']);

                return false;
            }

            sleep($this->retry);

            $this->retry *= 2;
            $this->update();
        }

        return isset($content['success']) && $content['success'] === 1;
    }

}
