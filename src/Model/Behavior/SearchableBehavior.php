<?php
declare(strict_types=1);

/**
 * Behavior que vincula duas Table, sendo a primeira a fonte dos
 * dados e a segunda o destino das informações para indexação FTS.
 *
 * A função do behavior é manter os dois datasources sincronizados
 *  - Adição de linha na fonte, reflete em nova linha indexada
 *  - Alteração deve ser propagada
 *  - Exclusão deve ser propagada
 */
namespace Autopage\PgSearch\Model\Behavior;

use ArrayObject;
use Autopage\PgSearch\Exception\DeindexException;
use Autopage\PgSearch\Exception\IndexException;
use Autopage\PgSearch\Exception\SetupException;
use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Throwable;

class SearchableBehavior extends Behavior
{
    use LocatorAwareTrait;

    /**
     * Configurações disponíveis
     *
     *  - target: string. Nome do repositório (Table) que deve ser usada para
     *  persistir os registros de forma pesquisável. Por padrão usa uma Table com
     *  nome igual a que está vinculada ao Behavior, adicionado o sufixo
     *  'Searches'. Exemplo: Posts -> PostsSearches.
     *  - foreign_key: string. Nome da coluna em _target_ que referencia o registro
     *  original. Por padrão usa o nome da tabela no singular adicionado o sufixo '_id'.
     *  Exemplo: Posts -> post_id
     *  - mapper: callable. Método/função que converte uma entidade de source para uma
     *  de target.
     *  - doIndex: bool|callable. Se deve ou não indexar o registro. Permite um
     *  controle individual.
     *  - doDeindex: bool|callable. Se deve ou não desindexar o registro. Permite um
     *  controle individual e suporte a remoção lógica (soft-delete).
     *
     * @var array
     */
    protected $_defaultConfig = [
        'target' => null,
        'foreign_key' => null,
        'mapper' => null,
        'doIndex' => true,
        'doDeindex' => false,
        'implementedMethods' => [
            'indexEntity' => 'indexEntity',
            'deindexEntity' => 'deindexEntity',
        ],
    ];

    /**
     * Determina se a entidade sendo registrada/alterada precisa
     * ser indexada ou desindexada - ou ainda, nenhum dos dois.
     *
     * Invoca os métodos apropriados caso necessário.
     *
     * @param  \Cake\Event\EventInterface       $event Evento capturado
     * @param  \Cake\Datasource\EntityInterface $entity Entitdade relacionada ao behavior
     * @param  \ArrayObject                     $options Opções extras opcionais
     * @return bool
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        // Verifica se o registro deve passar pelo behavior
        $doIndex = $this->getConfig('doIndex');
        if (is_callable($doIndex)) {
            $doIndex = $doIndex($entity);
        }

        if (!$doIndex) {
            return true;
        }

        // Verifica se é um caso para desindexar
        $doDeindex = $this->getConfig('doDeindex');
        if (is_callable($doDeindex)) {
            $doDeindex = $doDeindex($entity);
        }

        if ($doDeindex) {
            $this->deindexEntity($entity);

            return true;
        }

        $this->indexEntity($entity);

        return true;
    }

    /**
     * Exclui o registro indexado relacionado ao registro excluido
     *
     * @param  \Cake\Event\EventInterface       $event Evento capturado
     * @param  \Cake\Datasource\EntityInterface $entity Entitdade relacionada ao behavior
     * @param  \ArrayObject                     $options Opções extras opcionais
     * @return bool
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        $this->deindexEntity($entity);

        return true;
    }

    /**
     * Envia dados do registro $entity para o índice apropriado
     *
     * @param  \Cake\Datasource\EntityInterface $entity Entitdade relacionada ao behavior
     * @return void
     */
    public function indexEntity(EntityInterface $entity): void
    {
        $target = $this->getRepository();
        $sourceName = $entity->getSource();
        $pk = $entity->get($this->getSourcePk());

        try {
            $entry = $this->buildEntry($entity);
            if (empty($entry)) {
                return;
            }

            if ($target->save($entry)) {
                return;
            }
        } catch (SetupException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new IndexException("Falha ao indexar o registro '{$pk}' de '{$sourceName}'. \nMotivo: " . $e->getMessage(), 500, $e);
        }

        throw new IndexException("Não foi possível indexar o registro '{$pk}' de '{$sourceName}'.");
    }

    /**
     * Exclui um registro do índice a partir da entidade relacionada
     *
     * @param  \Cake\Datasource\EntityInterface $entity Instância do registro
     * relacionado.
     * @return void
     */
    public function deindexEntity(EntityInterface $entity): void
    {
        $repository = $this->getRepository();
        $sourceName = $entity->getSource();
        $pk = $entity->get($this->getSourcePk());
        $fk = $this->getRepositoryFk();

        if (empty($pk)) {
            return;
        }

        try {
            $repository->deleteAll([$fk => $pk]);
        } catch (Throwable $e) {
            throw new DeindexException("Falha ao excluir o registro vinculado a '{$pk}' de '{$sourceName}'. \nMotivo: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Define um finder capaz de construir uma query básica
     * com highlight e ranqueamento via FTS.
     *
     * @param  \Cake\ORM\Query  $query   Query original
     * @param  array  $options Configurações desse finder:
     *  - field: Nome do campo do tipo tsvector onde a busca será feita
     *  - value: Valor que deve ser comparado com o campo
     *  - highlight: Flag indicando se deve ou não incluir destaque nos termos encontrados. Por padrão
     *  é desativado.
     *  - highlight_field: Nome do campo textual onde o highlight será aplicado
     *  - exact: Se a comparação será do tipo exata ou aproximada. Por padrão é aproximada.
     *  - ts_function: Caso seja necessário utilizar uma função diferente da aproximada ou exata para parseamento.
     *  - configuration: Nome da configuração de busca usada na comparação. Por padrão, usa a mesma definida
     *  em PgSearch.config_name.
     *  - ranked: Flag indicando se a query deve ser ordenada por score FTS.
     * @return \Cake\ORM\Query
     */
    public function findFts(Query $query, array $options): Query
    {
        $defaultOptions = [
            'field' => $this->getRepository()->getDisplayField(),
            'value' => '',
            'highlight' => false,
            'highlight_field' => null,
            'exact' => false,
            'ts_function' => '',
            'configuration' => Configure::read('PgSearch.config_name', null),
            'ranked' => true,
        ];

        $options += $defaultOptions;

        $query->repository($this->getRepository());
        $query->enableAutoFields();
        $query->addDefaultTypes($this->getRepository());

        $searchConfig = $options['configuration'];
        $tsFunction = $options['ts_function'] ?: ($options['exact'] ? 'phraseto_tsquery' : 'plainto_tsquery');
        $field = $options['field'];
        $highlightField = $options['highlight_field'];

        $tsQuery = null;
        if (!empty($options['value'])) {
            $value = $options['value'];
            $prepend = $searchConfig ? "'{$searchConfig}', " : '';
            $tsQuery = "{$tsFunction}({$prepend}:search_value)";
        }

        $selectedFields = [];
        if ($options['highlight']) {
            $headlineParams = [];
            if ($searchConfig) {
                $headlineParams[] = $searchConfig; // Configuração para fazer o match
            }

            $headlineParams += [
                $highlightField => 'literal',  // Campo original para marcação
                $tsQuery => 'literal', // Query para buscar o resultado e posições
            ];

            $headlineParams[] = 'MaxFragments=3, MaxWords=50, MinWords=5, StartSel="<strong>", StopSel="</strong>",FragmentDelimiter="[...]"', // Configurações de formatação

            $selectedFields['highlight'] = $query->func()->ts_headline($headlineParams); // @phpstan-ignore-line
        }

        if ($options['ranked']) {
            $selectedFields['_rank'] = $query->func()->ts_rank_cd([ // @phpstan-ignore-line
                $field => 'literal', // Campo ts_vector para rankear
                $tsQuery => 'literal', // Query para o rankeamento
                '2|4' => 'literal', // Normalização
                                    // 2 = divides the rank by the document length;
                                    // 4 = divides the rank by the mean harmonic distance between extents
            ]);
        }

        if (!empty($selectedFields)) {
            $query->select($selectedFields);
        }

        $query->where(function (QueryExpression $exp, Query $q) use ($tsQuery, $field) {
            $conditions = [];

            if ($tsQuery) {
                $conditions[] = "{$field} @@ {$tsQuery}";
            }

            if (empty($conditions)) {
                return [];
            }

            return $exp->and($conditions);
        });

        if ($tsQuery) {
            $query->bind(':search_value', $options['value'], 'string');
        }

        if ($options['ranked']) {
            $order = [];
            if (Hash::get($query->clause('select'), '_rank') !== null) {
                $order['_rank'] = 'desc';
            }

            $query->order($order);
        }

        return $query;
    }

    /**
     * Recupera a instância do repositório onde a indexação é feita
     *
     * Utiliza primeiro a configuração 'target' como nome do repositório.
     * Se não estiver configurado, utiliza com fallback o nome da tabela
     * vinculada + sufixo 'Searches'.
     *
     * @return \Cake\ORM\Table
     */
    public function getRepository(): Table
    {
        $source = $this->table()->getAlias();
        $target = $this->getConfig('target') ?: $source . 'Searches';

        return $this->getTableLocator()->get($target);
    }

    /**
     * Determina o nome da coluna que vincula um registro na sua tabela
     * original 'source' e outro na tabela de indexação 'target'.
     *
     * Utiliza primeiro a configuração 'foreign_key' como nome da coluna.
     * Se não estiver configurado, utiliza com fallback o nome da tabela
     * vinculada na forma singular + sufixo '_' + nome da coluna chave primária.
     *
     * Exemplo:
     *  Registro na tabela Documentos, com coluna PK 'id'
     *  FK = documento_id
     *
     * @return string
     */
    public function getRepositoryFk(): string
    {
        $fk = $this->getConfig('foreign_key');
        if (!$fk) {
            $fk = Inflector::singularize($this->table()->getTable()) . '_' . $this->getSourcePk();
        }

        return $fk;
    }

    /**
     * Determina o nome da coluna primária na tabela original
     * Se for uma chave composta, considera sempre a primeira.
     *
     * @return string
     */
    public function getSourcePk(): string
    {
        $pk = $this->table()->getPrimaryKey();
        if (is_array($pk)) {
            $pk = $pk[0];
        }

        return $pk;
    }

    /**
     * Cria uma entrada para o repositório indexado.
     * Se configurado, utiliza o mapper.
     *
     * @param  \Cake\Datasource\EntityInterface $entity Entidade original
     * @return \Cake\Datasource\EntityInterface $entry
     */
    protected function buildEntry(EntityInterface $entity): EntityInterface
    {
        $mapper = $this->getConfig('mapper');
        if ($mapper && !is_callable($mapper)) {
            throw new SetupException('O mapper informado precisa ser callable. Mapper: ' . print_r($mapper, true));
        }

        $defaultMapper = function ($entity) {
            $entry = $this->getRepository()->newEntity($entity->toArray());
            $entry->set($this->getRepositoryFk(), $entity->get($this->getSourcePk()));

            return $entry;
        };

        if (!$mapper) {
            $mapper = $defaultMapper;
        }

        $target = $this->getRepository();
        $entry = call_user_func($mapper, $entity);
        if (!is_a($entry, $target->getEntityClass())) {
            throw new SetupException("O resultado do mapper deve ser uma instância compatível com '{$target->getEntityClass()}'.");
        }

        $pk = $target->getPrimaryKey();
        if (is_array($pk)) {
            $pk = $pk[0];
        }

        if ($entry->get($pk) !== null) {
            return $entry;
        }

        $exists = $target->find()
            ->where([
                $this->getRepositoryFk() => $entity->get($this->getSourcePk()),
            ])
            ->first();

        if ($exists) {
            $entry->set($pk, $exists->get($pk));
        }

        return $entry;
    }
}
