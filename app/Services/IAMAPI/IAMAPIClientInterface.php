<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Services\IAMAPI;

interface IAMAPIClientInterface
{
    public function getAccessGroupIdsByUserKey(string $user_key): mixed;
    public function getName(): string;
}
