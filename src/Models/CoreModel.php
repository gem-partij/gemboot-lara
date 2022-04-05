<?php
namespace Gemboot\Models;

use Illuminate\Database\Eloquent\Model;

use Gemboot\Contracts\CoreModelInterface as CoreModelContract;
use Gemboot\Traits\MainModelAbilities;

abstract class CoreModel extends Model implements CoreModelContract
{
    use MainModelAbilities;
}
