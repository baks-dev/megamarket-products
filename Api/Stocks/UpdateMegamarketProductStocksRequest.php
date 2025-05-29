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

namespace BaksDev\Megamarket\Products\Api\Stocks;

use BaksDev\Megamarket\Api\Megamarket;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Exception;
use InvalidArgumentException;
use stdClass;

final class UpdateMegamarketProductStocksRequest extends Megamarket
{
    private const bool STOP_SALES = false;

    private ?string $article = null;

    private ?int $total = null;

    public function article(string $article): self
    {
        $this->article = $article;
        return $this;
    }

    public function total(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function update(): bool
    {
        if(false === ($this->getProfile() instanceof UserProfileUid) || $this->isExecuteEnvironment() === false)
        {
            $this->logger->critical('Запрос может быть выполнен только для активного UserProfileUid и PROD окружении', [self::class.':'.__LINE__]);

            return true;
        }

        if(empty($this->article))
        {
            throw new InvalidArgumentException('Invalid Argument $article');
        }

        if($this->total === null)
        {
            throw new InvalidArgumentException('Invalid Argument $total');
        }

        try
        {
            $response = $this->TokenHttpClient()->request(
                'POST',
                '/api/merchantIntegration/v1/offerService/stock/update',
                ['json' => [
                    'meta' => new stdClass(),
                    'data' => [
                        'token' => $this->getToken(),
                        'stocks' => [
                            [
                                "offerId" => $this->article,
                                "quantity" => self::STOP_SALES === true ? 0 : max($this->total, 0)
                            ]
                        ]
                    ]
                ]]
            );

            $content = $response->toArray(false);
        }
        catch(Exception $exception)
        {
            $this->logger->critical(sprintf('megamarket: Ошибка обновления остатков артикула %s', $this->article), [$exception->getMessage()]);
            return false;
        }

        /** Статус всегда возвращает 200, делаем ретрай сами */
        if(isset($content['error']))
        {
            $this->logger->critical(sprintf('megamarket: Ошибка обновления остатков артикула %s', $this->article), $content['error']);
            return false;
        }

        return isset($content['success']) && $content['success'] === 1;
    }
}
