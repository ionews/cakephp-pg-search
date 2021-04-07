<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Database\Type;

use Cake\Core\Configure;
use Cake\Database\DriverInterface;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\ExpressionInterface;
use Cake\Database\Type\BaseType;
use Cake\Database\Type\ExpressionTypeInterface;
use InvalidArgumentException;

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

        $this->searchConfig = Configure::read('PgSearch.config_name');
    }

    /**
     * Converte para array apenas para ter um formato válido, mesmo que
     * não tenha significado prático.
     *
     * tsvector -> lista dos tokens, sem suas posições
     *
     * @param  string|null $value  Valor do banco
     * @param  \Cake\Database\DriverInterface $driver Instãncia do driver (Postgres)
     * @return array|null Lista de tokens
     */
    public function toPHP($value, DriverInterface $driver)
    {
        if (empty($value)) {
            return null;
        }

        $tokens = [];
        foreach (explode(' ', $value) as $item) {
            if (strpos($item, ':') === false) {
                throw new InvalidArgumentException(sprintf(
                    'The value `%s` is not a valid tsvector',
                    $value
                ));
            }

            [$token, $pos] = explode(':', $item);
            $token = trim($token, '\'');
            $pos = explode(',', $pos);
            $tokens[$token] = array_map('intval', $pos);
        }

        return $tokens;
    }

    /**
     * @inheritDoc
     */
    public function manyToPHP(array $values, array $fields, DriverInterface $driver): array
    {
        foreach ($fields as $field) {
            if (!isset($values[$field])) {
                continue;
            }

            $values[$field] = $this->toPHP($values[$field], $driver);
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    public function marshal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return implode(' ', $value);
        }

        return (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function toDatabase($value, DriverInterface $driver)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return implode(' ', $value);
        }

        return (string)$value;
    }

    /**
     * Converte a string do PHP para uma instância que representa
     * a função do Postgres 'to_tsvector'.
     *
     * Caso seja passada um array, faz a conversão usando implode, sem
     * nenhum tratamento especial.
     *
     * @param  string|array $value Valor para ser persistido
     * @return \Cake\Database\ExpressionInterface
     */
    public function toExpression($value): ExpressionInterface
    {
        $value = $this->marshal($value);

        $params = [];
        if ($this->searchConfig) {
            $params[] = $this->searchConfig;
        }

        $params[] = $value;

        return new FunctionExpression('to_tsvector', $params);
    }
}
