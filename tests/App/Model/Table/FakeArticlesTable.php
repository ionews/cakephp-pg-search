<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\App\Model\Table;

use Cake\ORM\Table;

class FakeArticlesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->setTable('articles_searches');
    }
}
