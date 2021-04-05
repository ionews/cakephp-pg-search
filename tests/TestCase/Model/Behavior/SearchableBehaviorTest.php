<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Model\Behavior;

use Cake\TestSuite\TestCase;

/**
 * Test for the Searchable Behavior.
 */
class SearchableBehaviorTest extends TestCase
{
    /**
     * fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'core.Articles',
        'plugin.Autopage\PgSearch.ArticlesSearches',
    ];

    /**
     * Tests behavior with default options
     *
     * @return void
     */
    public function testDefaultOptions()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable');
        $behavior = $table->behaviors()->get('Searchable');

        $result = get_class($behavior->getRepository());
        $expected = 'Cake\\ORM\\Table';

        $result = $behavior->getRepository()->getTable();
        $expected = 'articles_searches';
        $this->assertSame($expected, $result);

        $result = $behavior->getRepositoryFk();
        $expected = 'article_id';
        $this->assertSame($expected, $result);

        $result = $behavior->getSourcePk();
        $expected = 'id';
        $this->assertSame($expected, $result);
    }

    /**
     * Tests behavior with overrided options
     *
     * @return void
     */
    public function testOverrideOptions()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable', [
            'target' => \Autopage\PgSearch\Test\App\Model\Table\FakeArticlesTable::class,
            'foreign_key' => 'override_id',
        ]);
        $behavior = $table->behaviors()->get('Searchable');

        $result = get_class($behavior->getRepository());
        $expected = \Autopage\PgSearch\Test\App\Model\Table\FakeArticlesTable::class;
        $this->assertSame($expected, $result);

        $result = $behavior->getRepository()->getTable();
        $expected = 'articles_searches';
        $this->assertSame($expected, $result);

        $result = $behavior->getRepositoryFk();
        $expected = 'override_id';
        $this->assertSame($expected, $result);

        $result = $behavior->getSourcePk();
        $expected = 'id';
        $this->assertSame($expected, $result);
    }

    /**
     * Tests tables and fixtures initial state
     *
     * @return void
     */
    public function testVerifyPreconditions()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable');
        $behavior = $table->behaviors()->get('Searchable');

        $this->assertSame(3, $table->find()->count());

        $current = $table->get(1);
        $currentBody = $current->get('body');
        $expected = 'First Article Body';
        $this->assertSame($expected, $currentBody);

        $behaviorTable = $behavior->getRepository();
        $this->assertSame(1, $behaviorTable->find()->count());

        $currentIndexed = $behaviorTable->find()->where(['article_id' => 1])->first();
        $this->assertSame(1, $currentIndexed->get('id'));
    }

    /**
     * Tests indexing without a proper mapper
     *
     * @return void
     */
    public function testIndexWithoutMapper()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable');
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);
        $table->save($article);

        $articleIndexed = $behaviorTable->find()->where(['article_id' => 2])->first();
        $this->assertSame($bodyMsg, $articleIndexed->get('body'));
    }
}
