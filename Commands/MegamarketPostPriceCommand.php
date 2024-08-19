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
use BaksDev\Megamarket\Products\Repository\AllProducts\MegamarketAllProductInterface;
use BaksDev\Megamarket\Repository\AllProfileToken\AllProfileMegamarketTokenInterface;
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Получаем карточки товаров и добавляем отсутствующие
 */
#[AsCommand(
    name: 'baks:megamarket:price',
    description: 'Обновляет все цены на продукцию'
)]
class MegamarketPostPriceCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly MegamarketAllProductInterface $allProductPrice,
        private readonly AllProfileMegamarketTokenInterface $allProfileMegamarketToken,
        private readonly MessageDispatchInterface $messageDispatch,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('article', 'a', InputOption::VALUE_OPTIONAL, 'Фильтр по артикулу ((--article=... || -a ...))');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        /** Получаем активные токены авторизации профилей Yandex Market */
        $profiles = $this->allProfileMegamarketToken
            ->onlyActiveToken()
            ->findAll();

        $profiles = iterator_to_array($profiles);

        $helper = $this->getHelper('question');

        $questions[] = 'Все';

        foreach($profiles as $quest)
        {
            $questions[] = $quest->getAttr();
        }

        $question = new ChoiceQuestion(
            'Профиль пользователя',
            $questions,
            0
        );

        $profileName = $helper->ask($input, $output, $question);

        if($profileName === 'Все')
        {
            /** @var UserProfileUid $profile */
            foreach($profiles as $profile)
            {
                $this->update($profile, $input->getOption('article'));
            }
        }
        else
        {
            $UserProfileUid = null;

            foreach($profiles as $profile)
            {
                if($profile->getAttr() === $profileName)
                {
                    /* Присваиваем профиль пользователя */
                    $UserProfileUid = $profile;
                    break;
                }
            }

            if($UserProfileUid)
            {
                $this->update($UserProfileUid, $input->getOption('article'));
            }
        }

        $this->io->success('Цены Megamarket успешно обновлены');

        return Command::SUCCESS;
    }

    public function update(UserProfileUid $profile, ?string $article = null): void
    {
        $this->io->note(sprintf('Обновляем профиль %s', $profile->getAttr()));

        $allProducts = $this->allProductPrice->findAll();

        foreach($allProducts as $product)
        {
            /** Если передан артикул - фильтруем по вхождению */
            if(isset($article) && stripos($product['product_article'], $article) === false)
            {
                continue;
            }

            if(empty($product['product_price']))
            {
                $this->io->warning(
                    sprintf('Не указана стоимость артикула %s', $product['product_article'])
                );

                continue;
            }

            $currency = new Currency($product['product_currency']);

            if(
                empty($product['product_parameter_length']) ||
                empty($product['product_parameter_width']) ||
                empty($product['product_parameter_height']) ||
                empty($product['product_parameter_weight'])
            ) {

                $this->io->warning(
                    sprintf('Не указаны параметры упаковки артикула %s', $product['product_article'])
                );

                continue;
            }

            /**
             * Перерасчет стоимости продукции
             */

            // 15% комиссии
            $percent = $product['product_price'] / 100 * 15;

            // длина + ширина + высота * 5 и переводим с копейками * 100
            $rate = ($product['product_parameter_length'] + $product['product_parameter_width'] + $product['product_parameter_height']) / 2 * 100;
            $result_price = $product['product_price'] + $percent + $rate;
            $price = new Money($result_price, true);

            $MegamarketProductPriceMessage = new MegamarketProductPriceMessage(
                $profile,
                $product['product_article'],
                $price,
                $currency
            );

            $this->messageDispatch->dispatch($MegamarketProductPriceMessage);
            $this->io->text(sprintf('Обновили стоимость артикула %s', $product['product_article']));
        }
    }
}
