<?php
declare(strict_types=1);

namespace PgSearch\Database\Type;

use Cake\Core\Configure;
use Cake\Database\DriverInterface;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\ExpressionInterface;
use Cake\Database\Type\BaseType;
use Cake\Database\Type\ExpressionTypeInterface;

/**
 * Mapeia o tipo tsvector para a função/expressão
 * correspondente no banco de dados.
 */
class TsvectorType extends BaseType implements ExpressionTypeInterface
{
    /**
     * Nome da configuração de busca que deve ser usada
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * @inheritDoc
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->searchConfig = Configure::read('PgSearch.fts_config');
    }

    /**
     * Converte para array apenas para ter um formato válido, mesmo que
     * não tenha significado prático.
     *
     * tsvector -> lista dos tokens, sem suas posições
     *
     * @param  string|null $value  Valor do banco
     * @param  \Cake\Database\DriverInterface $driver Instãncia do driver (Postgres)
     * @return array Lista de tokens
     */
    public function toPHP($value, DriverInterface $driver)
    {
        $tokens = [];
        if (!empty($value)) {
            foreach (explode(' ', $value) as $item) {
                [$token, $posicoes] = explode(':', $item);
                $tokens[] = trim($token, '\'');
            }
        }

        return $tokens;
    }

    /**
     * @inheritDoc
     */
    public function marshal($value)
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toDatabase($value, DriverInterface $driver)
    {
        if (is_array($value)) {
            return implode(' ', $value);
        }

        return $value;
    }

    /**
     * Converte a string do PHP para uma instância que representa
     * a função do Postgres 'to_tsvector'.
     *
     * Caso seja passada um array, faz a conversão usando implode, sem
     * nenhum tratamento especial.
     *
     * @param  string|array|null $value Valor para ser persistido
     * @return \Cake\Database\ExpressionInterface
     */
    public function toExpression($value): ExpressionInterface
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $params = [];
        if ($this->searchConfig) {
            $params[] = $this->searchConfig;
        }

        $params[] = $value;

        return new FunctionExpression('to_tsvector', $params);
    }
}
