<?php
/**
 * Created by PhpStorm.
 * User: jon
 * Date: 7/26/17
 * Time: 12:13 PM
 */

namespace App\Transformers;

use League\Fractal\TransformerAbstract;
use League\Fractal\Scope;
use Route;

class BaseTransformer extends TransformerAbstract
{
    protected ?Scope $currentScope;

    protected $version;
    protected $route;
    protected $i10n;
    protected $continent;

    public function __construct()
    {
        $this->version = checkParam('v', true);
        $this->i10n = checkParam('i10n') ?? 'eng';
        $this->continent = $_GET['continent'] ?? false;
        $this->route = Route::currentRouteName() ?? '';
    }
}
