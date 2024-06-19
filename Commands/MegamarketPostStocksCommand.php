<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BaksDev\Megamarket\Products\Commands;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductPriceUpdate\MegamarketProductPriceMessage;
use BaksDev\Megamarket\Products\Messenger\MegamarketProductStocksUpdate\MegamarketProductStocksMessage;
use BaksDev\Megamarket\Products\Repository\AllPrice\MegamarketAllProductPriceInterface;
use BaksDev\Reference\Currency\Type\Currency;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Yandex\Market\Products\Entity\Card\YaMarketProductsCard;
use BaksDev\Yandex\Market\Products\Repository\Card\ProductsNotExistsYaMarketCard\ProductsNotExistsYaMarketCardInterface;
use BaksDev\Yandex\Market\Products\UseCase\Cards\NewEdit\Market\YaMarketProductsCardMarketDTO;
use BaksDev\Yandex\Market\Products\UseCase\Cards\NewEdit\YaMarketProductsCardDTO;
use BaksDev\Yandex\Market\Products\UseCase\Cards\NewEdit\YaMarketProductsCardHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Получаем карточки товаров и добавляем отсутствующие
 */
#[AsCommand(
    name: 'baks:megamarket:stocks',
    description: 'Обновляет все остатки на продукцию'
)]
class MegamarketPostStocksCommand extends Command
{
    private MegamarketAllProductPriceInterface $allProductPrice;
    private MessageDispatchInterface $messageDispatch;
    private LoggerInterface $logger;

    public function __construct(
        MegamarketAllProductPriceInterface $allProductPrice,
        MessageDispatchInterface $messageDispatch,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->allProductPrice = $allProductPrice;
        $this->messageDispatch = $messageDispatch;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addArgument('profile', InputArgument::OPTIONAL, 'Идентификатор профиля');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profile = $input->getArgument('profile');

        if(!$profile)
        {
            $io->error("Не указан идентификатор профиля пользователя. Пример:".PHP_EOL
                ." php bin/console baks:megamarket:stocks <UID>");
            return Command::INVALID;
        }

        $profile = new UserProfileUid($profile);

        $allProducts = $this->allProductPrice->findAll();

        foreach($allProducts as $product)
        {
            /** Если не указана стоимость - остаток 0 */
            $quantity = $product['product_price'] ? $product['product_quantity'] : 0;

            /** Если не указаны параметры упаковки - остаток 0 */
            if(
                empty($product['product_parameter_length']) ||
                empty($product['product_parameter_width']) ||
                empty($product['product_parameter_height']) ||
                empty($product['product_parameter_weight'])
            ) {
                $quantity = 0;

                $this->logger->critical(
                    sprintf('Не указаны параметры упаковки артикула %s', $product['product_article'])
                );
            }

            $MegamarketProductStocksMessage = new MegamarketProductStocksMessage(
                $profile,
                $product['product_article'],
                $quantity
            );

            $this->messageDispatch->dispatch($MegamarketProductStocksMessage, transport: 'megamarket-products');

        }

        return Command::SUCCESS;
    }

}
