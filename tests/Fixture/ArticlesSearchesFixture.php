<?php
namespace Autopage\PgSearch\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Short description for class.
 */
class ArticlesSearchesFixture extends TestFixture
{
    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'article_id' => ['type' => 'integer', 'null' => true],
        'body' => ['type' => 'tsvector'],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];

    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['article_id' => 1, 'body' => 'First Article Indexed Body'],
    ];
}
