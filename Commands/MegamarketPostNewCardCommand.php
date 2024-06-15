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

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Yandex\Market\Products\Entity\Card\YaMarketProductsCard;
use BaksDev\Yandex\Market\Products\Repository\Card\ProductsNotExistsYaMarketCard\ProductsNotExistsYaMarketCardInterface;
use BaksDev\Yandex\Market\Products\UseCase\Cards\NewEdit\Market\YaMarketProductsCardMarketDTO;
use BaksDev\Yandex\Market\Products\UseCase\Cards\NewEdit\YaMarketProductsCardDTO;
use BaksDev\Yandex\Market\Products\UseCase\Cards\NewEdit\YaMarketProductsCardHandler;
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
    name: 'baks:yandex-market-products:post:new',
    description: 'Выгружает новые карточки на Megamarket')
]
class MegamarketPostNewCardCommand extends Command
{
    private ProductsNotExistsYaMarketCardInterface $productsNotExistsYaMarketCard;
    private YaMarketProductsCardHandler $marketProductsCardHandler;

    public function __construct(
        ProductsNotExistsYaMarketCardInterface $productsNotExistsYaMarketCard,
        YaMarketProductsCardHandler $marketProductsCardHandler,
    )
    {
        parent::__construct();

        $this->productsNotExistsYaMarketCard = $productsNotExistsYaMarketCard;
        $this->marketProductsCardHandler = $marketProductsCardHandler;
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
                ." php bin/console baks:yandex-market-products:post:new <UID>");
            return Command::INVALID;
        }

        $profile = new UserProfileUid($profile);


        /** Получаем все новые карточки, которых нет в маркете */
        $YaMarketProductsCardMarket = $this->productsNotExistsYaMarketCard->findAll($profile);

        /** @var YaMarketProductsCardMarketDTO $YaMarketProductsCardMarketDTO */
        foreach($YaMarketProductsCardMarket as $i => $YaMarketProductsCardMarketDTO)
        {
            //            if ($i % 600 === 0) {
            //                $io->info($i.': Исключаем блокировку (ОГРАНИЧЕНИЕ! 600 запросов в минуту)');
            //                sleep(60);
            //            }

            // Исключаем блокировку (ОГРАНИЧЕНИЕ! 600 запросов в минуту)'
            usleep(300);

            $YaMarketProductsCardMarketDTO->setProfile($profile);

            $YaMarketProductsCardDTO = new YaMarketProductsCardDTO();
            $YaMarketProductsCardDTO->setMarket($YaMarketProductsCardMarketDTO);

            $YaMarketProductsCard = $this->marketProductsCardHandler->handle($YaMarketProductsCardDTO);

            if($YaMarketProductsCard instanceof  YaMarketProductsCard)
            {
                $io->success(sprintf('Добавили карточку с артикулом %s', $YaMarketProductsCardMarketDTO->getSku()));
            }
            else
            {
                $io->warning(sprintf('%s: Ошибка при добавлении карточки с артикулом %s', $YaMarketProductsCard, $YaMarketProductsCardMarketDTO->getSku()));
            }
        }

        $io->success('Карточки успешно добавлены в очередь');

        return Command::SUCCESS;
    }

}