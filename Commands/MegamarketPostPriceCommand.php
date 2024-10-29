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
    name: 'baks:megamarket-products:post:price',
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


            $price = new Money($product['product_price'], true);

            $MegamarketProductPriceMessage = new MegamarketProductPriceMessage(
                $profile,
                $product['product_article'],
                $price,
                $currency
            );

            $MegamarketProductPriceMessage->setParameter(
                $product['product_parameter_width'],
                $product['product_parameter_height'],
                $product['product_parameter_length']
            );

            $this->messageDispatch->dispatch($MegamarketProductPriceMessage);
            $this->io->text(sprintf('Обновили стоимость артикула %s', $product['product_article']));
        }
    }
}
