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
use Autopage\PgSearch\Exception\IndexException;
use Autopage\PgSearch\Exception\ReindexException;
use Autopage\PgSearch\Exception\SetupException;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Inflector;
use RuntimeException;
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
     *  'Search'. Exemplo: Posts -> PostsSearch.
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
        'implementedMethods' => [],
    ];

    /**
     * Envia dados do registro criado/alterado para o índice
     *
     * @param  \Cake\Event\EventInterface       $event
     * @param  \Cake\Datasource\EntityInterface $entity
     * @param  \ArrayObject                     $options
     * @return bool
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        $sourceName = $entity->getSource();
        $target = $this->getRepository();
        $pk = $entity->get($this->getSourcePk());

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
            if (!$this->deleteIndexedByFk($entity)) {
                throw new DeindexException("Não foi possível excluir do índice o registro '{$pk}' de '{$sourceName}'.");
            }

            return true;
        }

        try {
            $entry = $this->buildEntry($entity);
            if (empty($entry)) {
                return true;
            }

            if ($target->save($entry)) {
                return true;
            }
        } catch (Throwable $e) {
            throw new IndexException("Falha ao indexar o registro '{$pk}' de '{$sourceName}'. \nMotivo: " . $e->getMessage(), 500, $e);
        }

        throw new IndexException("Não foi possível indexar o registro '{$pk}' de '{$sourceName}'.");
    }

    /**
     * Exclui o registro indexado relacionado ao registro excluido
     *
     * @param  \Cake\Event\EventInterface       $event
     * @param  \Cake\Datasource\EntityInterface $entity
     * @param  \ArrayObject                     $options
     * @return bool
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        $sourceName = $entity->getSource();
        $pk = $entity->get($this->getSourcePk());

        if (empty($pk)) {
            return true;
        }

        try {
            if ($this->deleteIndexedByFk($entity)) {
                return true;
            }
        } catch (Throwable $e) {
            throw new DeindexException("Falha ao excluir o registro vinculado a '{$pk}' de '{$sourceName}'. \nMotivo: " . $e->getMessage(), 500, $e);
        }

        throw new DeindexException("Não foi possível excluir do índice o registro vinculado a '{$pk}' em '{$sourceName}'.");
        return true;
    }

    /**
     * Recupera a instância do repositório onde a indexação é feita
     *
     * Utiliza primeiro a configuração 'target' como nome do repositório.
     * Se não estiver configurado, utiliza com fallback o nome da tabela
     * vinculada + sufixo 'Search'.
     *
     * @return \Cake\Datasource\RepositoryInterface
     */
    public function getRepository(): RepositoryInterface
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

    /**
     * Exclui um registro do índice a partir da entidade relacionada
     *
     * @param  \Cake\Datasource\EntityInterface $entity Instância do registro
     * relacionado.
     * @return bool
     */
    protected function deleteIndexedByFk($entity): bool
    {
        $repository = $this->getRepository();
        $pk = $entity->get($this->getSourcePk());
        if (empty($pk)) {
            throw new DeindexException("Não é possível remover do índice registro sem chave primária.");
        }

        $fk = $this->getRepositoryFk();

        return $repository->deleteAll([$fk => $pk]) >= 0;
    }
}
