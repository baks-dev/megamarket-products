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

use BaksDev\Yandex\Market\Products\Messenger\Card\YaMarketProductsCardMessage;
use BaksDev\Yandex\Market\Products\Type\Card\Event\YaMarketProductsCardEventUid;
use BaksDev\Yandex\Market\Products\Type\Card\Id\YaMarketProductsCardUid;

final class MegamarketProductPriceMessage
{
    /**
     * Идентификатор
     */
    private YaMarketProductsCardUid $id;

    /**
     * Идентификатор события
     */
    private YaMarketProductsCardEventUid $event;

    /**
     * Идентификатор предыдущего события
     */
    private ?YaMarketProductsCardEventUid $last;


    public function __construct(YaMarketProductsCardMessage $message)
    {
        $this->id = $message->getId();
        $this->event = $message->getEvent();
        $this->last = $message->getLast();
    }


    /**
     * Идентификатор
     */
    public function getId(): YaMarketProductsCardUid
    {
        return $this->id;
    }


    /**
     * Идентификатор события
     */
    public function getEvent(): YaMarketProductsCardEventUid
    {
        return $this->event;
    }


    /**
     * Идентификатор предыдущего события
     */
    public function getLast(): ?YaMarketProductsCardEventUid
    {
        return $this->last;
    }
}