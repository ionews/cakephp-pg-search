<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Model\Behavior;

use Autopage\PgSearch\Exception\SetupException;
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
     * Tests indexing with the default mapper
     *
     * @return void
     */
    public function testIndexDefaultMapper()
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

    /**
     * Tests indexing without a valid mapper
     *
     * @return void
     */
    public function testIndexInvalidMapper()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable', ['mapper' => 'invalid mapper']);
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);

        $this->expectException(SetupException::class);
        $this->expectExceptionMessage('O mapper informado precisa ser callable. Mapper: invalid mapper');

        $table->save($article);
    }

    /**
     * Tests indexing with a invalid custom mapper
     *
     * @return void
     */
    public function testIndexInvalidMapperResponse()
    {
        $customMapper = function ($entity) {
            return [
                'article_id' => $entity->id,
                'body' => $entity->body,
            ];
        };

        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable', ['mapper' => $customMapper]);
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);

        $this->expectException(SetupException::class);
        $this->expectExceptionMessage("O resultado do mapper deve ser uma instância compatível com 'Cake\ORM\Entity'.");

        $table->save($article);
    }

    /**
     * Tests indexing with a custom mapper
     *
     * @return void
     */
    public function testIndexCustomMapper()
    {
        $customMapper = function ($entity) {
            $entry = $this->getTableLocator()->get('ArticlesSearches')->newEmptyEntity();
            $entry->body = mb_strtoupper($entity->body);
            $entry->article_id = $entity->id;

            return $entry;
        };

        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable', ['mapper' => $customMapper]);
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);

        $table->save($article);

        $articleIndexed = $behaviorTable->find()->where(['article_id' => 2])->first();
        $this->assertSame(mb_strtoupper($bodyMsg), $articleIndexed->get('body'));
    }

    /**
     * Tests skip index step
     *
     * @return void
     */
    public function testNoIndexSetupConfig()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable', [
            'doIndex' => function ($entity) {
                return $entity->id !== 2;
            },
        ]);
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);

        $table->save($article);

        $indexed = $behaviorTable->find()->where(['article_id' => 2])->first();
        $this->assertNull($indexed);
    }

    /**
     * Tests skip index step, part 2
     *
     * @return void
     */
    public function testNoIndexRuntimeConfig()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable');
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);
        $behavior->setConfig('doIndex', false);

        $table->save($article);

        $indexed = $behaviorTable->find()->where(['article_id' => 2])->first();
        $this->assertNull($indexed);
    }

    /**
     * Tests deindex using logical delete
     *
     * @return void
     */
    public function testDeindexOnSave()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable', [
            'doDeindex' => function ($entity) {
                return $entity->id === 1;
            },
        ]);
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        // Previous status
        $indexed = $behaviorTable->find()->where(['article_id' => 1])->first();
        $this->assertSame('First Article Indexed Body', $indexed->get('body'));

        $article = $table->get(1);
        $article->set('body', 'Change without index.');
        $table->save($article);

        // Final status
        $indexed = $behaviorTable->find()->where(['article_id' => 1])->first();
        $this->assertNull($indexed);

        $bodyMsg = 'That is the new body to index.';
        $article = $table->get(2);
        $article->set('body', $bodyMsg);

        $table->save($article);

        // Indexing when conditions of 'doDeindex' are false
        $indexed = $behaviorTable->find()->where(['article_id' => 2])->first();
        $this->assertSame($bodyMsg, $indexed->get('body'));
    }

    /**
     * Tests deindex using delete
     *
     * @return void
     */
    public function testDeindexOnDelete()
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Autopage/PgSearch.Searchable');
        $behavior = $table->behaviors()->get('Searchable');
        $behaviorTable = $behavior->getRepository();

        $article = $table->get(1);
        $table->delete($article);
        $indexed = $behaviorTable->find()->where(['article_id' => 1])->first();
        $this->assertNull($indexed);

        // Delete non-indexed
        $indexed = $behaviorTable->find()->where(['article_id' => 3])->first();
        $this->assertNull($indexed);
        $article = $table->get(3);
        $table->delete($article);
    }
}
