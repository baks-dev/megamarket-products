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

namespace BaksDev\Megamarket\Products\Controller\Public;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Repository\SettingsMain\SettingsMainInterface;
use BaksDev\Megamarket\Repository\MegamarketTokenByProfile\MegamarketTokenByProfileInterface;
use BaksDev\Products\Category\Repository\AllCategoryByMenu\AllCategoryByMenuInterface;
use BaksDev\Products\Product\Repository\AllProductsByCategory\AllProductsByCategoryInterface;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ProductsController extends AbstractController
{
    /**
     * Файл экспорта карточек Megamarket
     */
    #[Route('/megamarket/{profile}/products.xml', name: 'export.products', methods: ['GET'])]
    public function search(
        Request $request,
        SettingsMainInterface $settingsMain,
        AllCategoryByMenuInterface $allCategory,
        AllProductsByCategoryInterface $productsByCategory,
        MegamarketTokenByProfileInterface $megamarketTokenByProfile,
        #[MapEntity] UserProfile $profile,
    ): Response
    {

        $UserProfileUid = $profile->getId();

        $MegamarketAuthorization = $megamarketTokenByProfile->getToken($UserProfileUid);

        if(false === $MegamarketAuthorization)
        {
            throw new InvalidArgumentException('Page Not Found');
        }

        $response = $this->render(
            [
                'category' => $allCategory->findAll(),
                'settings' => $settingsMain->getSettingsMainAssociative($request->getHost(), $request->getLocale()),
                'products' => $productsByCategory->fetchAllProductByCategory()],
            file: 'export.html.twig'
        );

        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
