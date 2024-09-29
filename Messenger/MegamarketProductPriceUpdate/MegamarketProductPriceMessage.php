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

use BaksDev\Reference\Currency\Type\Currency;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class MegamarketProductPriceMessage
{
    /**
     * Профиль пользователя
     */
    private UserProfileUid $profile;

    /**
     * Артикул
     */
    private string $article;

    /**
     * Стоимость продукции
     */
    private ?Money $price;

    /**
     * Валюта
     */
    private Currency $currency;


    /** Параметры упаковки для расчета  */
    private int $width = 0;
    private int $height = 0;
    private int $length = 0;


    public function __construct(
        UserProfileUid|string $profile,
        string $article,
        Money|int|float|string|null $price,
        Currency|string $currency
    ) {

        if(!$profile instanceof UserProfileUid)
        {
            $profile = new UserProfileUid($profile);
        }

        if($price && !$price instanceof Money)
        {
            $price = new Money($price);
        }

        if(!$currency instanceof Currency)
        {
            $currency = new Currency($currency);
        }

        $this->article = $article;
        $this->price = $price;
        $this->currency = $currency;
        $this->profile = $profile;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /**
     * Article
     */
    public function getArticle(): string
    {
        return $this->article;
    }

    /**
     * Price
     */
    public function getPrice(): ?Money
    {
        return $this->price;
    }

    /**
     * Currency
     */
    public function getCurrency(): Currency
    {
        return $this->currency;
    }


    public function setParameter(int $width, int $height, int $length): void
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
    }

    /**
     * Width
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Height
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Length
     */
    public function getLength(): int
    {
        return $this->length;
    }

}
