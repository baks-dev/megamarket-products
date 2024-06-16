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
    private ?string $article = null;

    private ?int $price = null;

    public function article(string $article): self
    {
        $this->article = $article;
        return $this;
    }

    public function price(int|float|Money $price): self
    {
        if($price instanceof Money)
        {
            $price = $price->getOnlyPositive();
        }

        $this->price = (int) $price;

        return $this;
    }

    public function update(): bool
    {
        if(empty($this->article))
        {
            throw new InvalidArgumentException('Invalid Argument $article');
        }

        if(empty($this->price))
        {
            throw new InvalidArgumentException('Invalid Argument price');
        }


        $response = $this->TokenHttpClient()->request('POST',
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
            $content['error'][0] = __FILE__.':'.__LINE__;

            $this->logger->critical(sprintf('Ошибка обновления стоимости: %s', $this->article), $content['error']);

            return false;
        }

        return isset($content['success']) && $content['success'] === 1;
    }

}