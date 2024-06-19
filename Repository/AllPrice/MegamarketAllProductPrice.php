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

namespace BaksDev\Megamarket\Products\Repository\AllPrice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\DeliveryTransport\Entity\ProductParameter\DeliveryPackageProductParameter;
use BaksDev\Products\Product\Entity\Active\ProductActive;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Price\ProductOfferPrice;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Quantity\ProductOfferQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Price\ProductModificationPrice;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Quantity\ProductModificationQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Price\ProductVariationPrice;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Offers\Variation\Quantity\ProductVariationQuantity;
use BaksDev\Products\Product\Entity\Price\ProductPrice;
use BaksDev\Products\Product\Entity\Product;

final class MegamarketAllProductPrice implements MegamarketAllProductPriceInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    ) {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Метод получает всю имеющуюся продукцию, стоимость и наличие
     */
    public function findAll(): array|bool
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        //$dbal->select('id');
        $dbal->from(Product::class, 'product');

        $dbal
            //->addSelect('product_active.active')
            //->addSelect('product_active.active_from')
            //->addSelect('product_active.active_to')
            ->leftJoin(
                'product',
                ProductActive::class,
                'product_active',
                'product_active.event = product.event'
            );

        $dbal
            ->leftJoin(
                'product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id'
            );


        $dbal->leftJoin(
            'product',
            ProductOffer::class,
            'product_offer',
            'product_offer.event = product.event'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariation::class,
            'product_variation',
            'product_variation.offer = product_offer.id'
        );

        $dbal->leftJoin(
            'product_variation',
            ProductModification::class,
            'product_modification',
            'product_modification.variation = product_variation.id'
        );


        /**
         * Цена товара
         */

        $dbal
            ->leftJoin(
                'product',
                ProductPrice::class,
                'product_price',
                'product_price.event = product.event'
            );

        $dbal
            ->leftJoin(
                'product_offer',
                ProductOfferPrice::class,
                'product_offer_price',
                'product_offer_price.offer = product_offer.id'
            );

        $dbal->leftJoin(
            'product_variation',
            ProductVariationPrice::class,
            'product_variation_price',
            'product_variation_price.variation = product_variation.id'
        );


        /* Цена множественного варианта */
        $dbal->leftJoin(
            'product_modification',
            ProductModificationPrice::class,
            'product_modification_price',
            'product_modification_price.modification = product_modification.id'
        );

        $dbal->addSelect(
            '
			CASE
			   WHEN product_modification_price.price IS NOT NULL AND product_modification_price.price > 0 
			   THEN product_modification_price.price
			   
			   WHEN product_variation_price.price IS NOT NULL AND product_variation_price.price > 0 
			   THEN product_variation_price.price
			   
			   WHEN product_offer_price.price IS NOT NULL AND product_offer_price.price > 0 
			   THEN product_offer_price.price
			   
			   WHEN product_price.price IS NOT NULL AND product_price.price > 0 
			   THEN product_price.price
			   
			   ELSE NULL
			END AS product_price
		'
        );

        /** Валюта продукта */
        $dbal->addSelect(
            "
			CASE
			   WHEN product_modification_price.price IS NOT NULL AND product_modification_price.price > 0 
			   THEN product_modification_price.currency
			   
			   WHEN product_variation_price.price IS NOT NULL AND product_variation_price.price > 0 
			   THEN product_variation_price.currency
			   
			   WHEN product_offer_price.price IS NOT NULL AND product_offer_price.price > 0 
			   THEN product_offer_price.currency
			   
			   WHEN product_price.price IS NOT NULL AND product_price.price > 0
			   THEN product_price.currency
			   
			   ELSE NULL
			   
			END AS product_currency
		"
        );


        /** Наличие продукции */

        $dbal
            //->addSelect('SUM(product_variation_quantity.quantity) AS product_variation_quantity')
            ->leftJoin(
                'product_variation',
                ProductVariationQuantity::class,
                'product_variation_quantity',
                'product_variation_quantity.variation = product_variation.id'
            );


        $dbal
            //->addSelect('SUM(product_offer_quantity.quantity) AS product_offer_quantity')
            ->leftJoin(
                'product_offer',
                ProductOfferQuantity::class,
                'product_offer_quantity',
                'product_offer_quantity.offer = product_offer.id'
            );


        $dbal
            //->addSelect('SUM(product_modification_quantity.quantity) AS product_modification_quantity')
            ->leftJoin(
                'product_modification',
                ProductModificationQuantity::class,
                'product_modification_quantity',
                'product_modification_quantity.modification = product_modification.id'
            );


        /** Количественный учет */
        $dbal->addSelect(
            '
            CASE
            
                WHEN product_active.active IS NOT TRUE OR (product_active.active_from > NOW() AND (product_active.active_to IS NOT NULL AND product_active.active_to < NOW() ))
                THEN 0
            
			   WHEN product_modification_quantity.quantity > 0 AND product_modification_quantity.quantity > product_modification_quantity.reserve 
			   THEN (product_modification_quantity.quantity - product_modification_quantity.reserve)
			
			   WHEN product_variation_quantity.quantity > 0 AND product_variation_quantity.quantity > product_variation_quantity.reserve 
			   THEN (product_variation_quantity.quantity - product_variation_quantity.reserve)
			
			   WHEN product_offer_quantity.quantity > 0 AND product_offer_quantity.quantity > product_offer_quantity.reserve 
			   THEN (product_offer_quantity.quantity - product_offer_quantity.reserve)
			  
			   WHEN product_price.quantity > 0 AND product_price.quantity > product_price.reserve 
			   THEN (product_price.quantity - product_price.reserve)
			 
			   ELSE 0
			   
			END AS product_quantity
            
		'
        );


        /* Артикул продукта */

        $dbal->addSelect(
            '
			CASE
			   WHEN product_modification.article IS NOT NULL 
			   THEN product_modification.article
			   
			   WHEN product_variation.article IS NOT NULL 
			   THEN product_variation.article
			   
			   WHEN product_offer.article IS NOT NULL 
			   THEN product_offer.article
			   
			   WHEN product_info.article IS NOT NULL 
			   THEN product_info.article
			   
			   ELSE NULL
			END AS product_article
		'
        );

        $dbal
            ->addSelect('product_parameter.length AS product_parameter_length')
            ->addSelect('product_parameter.width AS product_parameter_width')
            ->addSelect('product_parameter.height AS product_parameter_height')
            ->addSelect('product_parameter.weight AS product_parameter_weight')
            ->leftJoin(
                'product_modification',
                DeliveryPackageProductParameter::class,
                'product_parameter',
                'product_parameter.product = product.id AND
            (product_parameter.offer IS NULL OR product_parameter.offer = product_offer.const) AND
            (product_parameter.variation IS NULL OR product_parameter.variation = product_variation.const) AND
            (product_parameter.modification IS NULL OR product_parameter.modification = product_modification.const)
        '
            );


        return $dbal
            // ->enableCache('Namespace', 3600
            ->fetchAllAssociative();
    }
}
